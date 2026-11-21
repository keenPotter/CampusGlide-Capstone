<?php

namespace App\Http\Controllers;

use App\Http\Resources\RescheduleRequestResource;
use App\Models\Allocation;
use App\Models\RescheduleRequest;
use App\Services\AllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Trip Modification Requests.
 *  - Faculty can only send a request (POST allocations/{id}/reschedule-requests).
 *  - Administrators approve or disapprove a request, or reassign the date directly with a reason.
 */
class RescheduleRequestController extends Controller
{
    private const WITH = ['trip.allocation', 'requester', 'handler'];

    public function __construct(protected AllocationService $allocations)
    {
    }

    /**
     * GET /api/reschedule-requests
     * Administrators see all. Faculty see only their own. Optional: ?status=pending
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = RescheduleRequest::with(self::WITH);

        if ($user->hasRole('faculty')) {
            $query->whereHas('trip.vehicleRequest', fn ($q) => $q->where('requester_id', $user->id));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($status === 'pending') {
            $query->orderBy('new_date')->orderBy('old_date')->orderBy('id');
        } else {
            $query->latest();
        }

        return RescheduleRequestResource::collection($query->limit(100)->get());
    }

    /**
     * POST /api/allocations/{allocation}/reschedule-requests
     * Faculty only (route middleware). Faculty ask for a change; they do not move the trip.
     */
    public function store(Request $request, Allocation $allocation)
    {
        $user = $request->user();

        $allocation->loadMissing('trip.vehicleRequest');
        $trip = $allocation->trip;

        abort_unless(
            (int) $trip?->vehicleRequest?->requester_id === (int) $user->id,
            403,
            'You may only request modifications for your own trips.'
        );

        $validated = $request->validate([
            'new_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time' => ['nullable', 'date_format:H:i'],
            'passengers' => ['nullable', 'integer', 'min:1'],
            'new_departure_time' => ['nullable', 'date_format:H:i'],
            'new_estimated_return_time' => ['nullable', 'date_format:H:i'],
            'new_destination' => ['nullable', 'string', 'max:255'],
            'new_purpose' => ['nullable', 'string', 'max:255'],
            'new_number_of_passengers' => ['nullable', 'integer', 'min:1'],
            'new_passengers' => ['nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        abort_unless(
            $trip->trip_status === 'scheduled',
            422,
            "A {$trip->trip_status} trip can no longer be modified."
        );

        abort_if(
            RescheduleRequest::where('trip_id', $trip->id)->where('status', 'pending')->exists(),
            422,
            'This trip already has a pending Trip Modification Request.'
        );

        $vehicleRequest = $trip->vehicleRequest;
        $oldDate = $trip->trip_date->toDateString();
        $newDate = $validated['new_date'] ?? $validated['date'] ?? $oldDate;
        $newDeparture = $validated['new_departure_time'] ?? $validated['time'] ?? substr((string) $trip->departure_time, 0, 5);
        $newReturn = $validated['new_estimated_return_time'] ?? ($trip->estimated_return_time ? substr((string) $trip->estimated_return_time, 0, 5) : null);
        $newDestination = $validated['new_destination'] ?? $trip->destination;
        $newPurpose = $validated['new_purpose'] ?? $trip->purpose;
        $newPassengers = $validated['new_number_of_passengers']
            ?? $validated['new_passengers']
            ?? $validated['passengers']
            ?? $vehicleRequest->number_of_passengers;

        abort_if(
            $newDate === $oldDate
                && $newDeparture === substr((string) $trip->departure_time, 0, 5)
                && $newReturn === ($trip->estimated_return_time ? substr((string) $trip->estimated_return_time, 0, 5) : null)
                && $newDestination === $trip->destination
                && $newPurpose === $trip->purpose
                && (int) $newPassengers === (int) $vehicleRequest->number_of_passengers,
            422,
            'No trip details were changed.'
        );

        $rescheduleRequest = RescheduleRequest::create([
            'trip_id' => $trip->id,
            'requested_by' => $user->id,
            'old_date' => $oldDate,
            'new_date' => $newDate,
            'old_departure_time' => substr((string) $trip->departure_time, 0, 5),
            'new_departure_time' => $newDeparture,
            'old_estimated_return_time' => $trip->estimated_return_time ? substr((string) $trip->estimated_return_time, 0, 5) : null,
            'new_estimated_return_time' => $newReturn,
            'old_destination' => $trip->destination,
            'new_destination' => $newDestination,
            'old_purpose' => $trip->purpose,
            'new_purpose' => $newPurpose,
            'old_number_of_passengers' => $vehicleRequest->number_of_passengers,
            'new_number_of_passengers' => $newPassengers,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return (new RescheduleRequestResource($rescheduleRequest->load(self::WITH)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/reschedule-requests/{rescheduleRequest}/approve
     * Moves the trip to the requested date. The conflict check runs first.
     * The administrator's note is optional.
     */
    public function approve(Request $request, RescheduleRequest $rescheduleRequest)
    {
        $this->assertPending($rescheduleRequest);

        $request->validate(['admin_reason' => ['nullable', 'string', 'max:500']]);

        DB::transaction(function () use ($request, $rescheduleRequest) {
            $trip = $rescheduleRequest->trip()->lockForUpdate()->first();
            $vehicleRequest = $trip->vehicleRequest()->lockForUpdate()->first();

            $oldDate = $trip->trip_date?->toDateString();
            $newDate = $rescheduleRequest->new_date?->toDateString();
            if ($newDate && $newDate !== $oldDate) {
                $this->allocations->moveTrip($trip, $newDate);
            }

            $tripUpdates = [];
            if ($rescheduleRequest->new_destination !== null
                && $rescheduleRequest->new_destination !== $rescheduleRequest->old_destination) {
                $tripUpdates['destination'] = $rescheduleRequest->new_destination;
            }
            if ($rescheduleRequest->new_purpose !== null
                && $rescheduleRequest->new_purpose !== $rescheduleRequest->old_purpose) {
                $tripUpdates['purpose'] = $rescheduleRequest->new_purpose;
            }
            if ($rescheduleRequest->new_departure_time !== null
                && $rescheduleRequest->new_departure_time !== $rescheduleRequest->old_departure_time) {
                $tripUpdates['departure_time'] = $rescheduleRequest->new_departure_time;
            }
            if ($rescheduleRequest->new_estimated_return_time !== null
                && $rescheduleRequest->new_estimated_return_time !== $rescheduleRequest->old_estimated_return_time) {
                $tripUpdates['estimated_return_time'] = $rescheduleRequest->new_estimated_return_time;
            }
            if ($tripUpdates) {
                $trip->update($tripUpdates);
            }

            if ($rescheduleRequest->new_number_of_passengers !== null
                && (int) $rescheduleRequest->new_number_of_passengers !== (int) $rescheduleRequest->old_number_of_passengers) {
                $vehicleRequest->update([
                    'number_of_passengers' => $rescheduleRequest->new_number_of_passengers,
                ]);
            }

            $rescheduleRequest->update([
                'status' => 'approved',
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
                'admin_reason' => $request->input('admin_reason'),
            ]);
        });

        return new RescheduleRequestResource($rescheduleRequest->load(self::WITH));
    }

    /**
     * POST /api/reschedule-requests/{rescheduleRequest}/disapprove
     * The trip is not touched. The reason is required, and the faculty member can see it.
     * (This replaces the old "cancel", which deleted the request.)
     */
    public function disapprove(Request $request, RescheduleRequest $rescheduleRequest)
    {
        $this->assertPending($rescheduleRequest);

        $validated = $request->validate([
            'admin_reason' => ['required', 'string', 'max:500'],
        ]);

        $rescheduleRequest->update([
            'status' => 'disapproved',
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
            'admin_reason' => $validated['admin_reason'],
        ]);

        return new RescheduleRequestResource($rescheduleRequest->load(self::WITH));
    }

    /**
     * POST /api/allocations/{allocation}/reschedule
     * Administrator reassigns the date directly. A reason is required.
     */
    public function reschedule(Request $request, Allocation $allocation)
    {
        $validated = $request->validate([
            'new_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $trip = $allocation->trip;
        $oldDate = $this->allocations->moveTrip($trip, $validated['new_date']);

        // A pending faculty request is no longer needed once the date is changed directly.
        RescheduleRequest::where('trip_id', $trip->id)->where('status', 'pending')->delete();

        $rescheduleRequest = RescheduleRequest::create([
            'trip_id' => $trip->id,
            'requested_by' => $request->user()->id,
            'old_date' => $oldDate,
            'new_date' => $validated['new_date'],
            'reason' => $validated['reason'],
            'status' => 'approved',
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return (new RescheduleRequestResource($rescheduleRequest->load(self::WITH)))
            ->response()
            ->setStatusCode(201);
    }

    private function assertPending(RescheduleRequest $rescheduleRequest): void
    {
        abort_unless($rescheduleRequest->status === 'pending', 422, "This request is already {$rescheduleRequest->status}.");
    }
}