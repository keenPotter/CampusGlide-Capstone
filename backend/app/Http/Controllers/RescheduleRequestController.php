<?php

namespace App\Http\Controllers;

use App\Http\Resources\RescheduleRequestResource;
use App\Models\Allocation;
use App\Models\Driver;
use App\Models\RescheduleRequest;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\AllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trip Modification Requests.
 *  - Faculty can only send a request (POST allocations/{id}/reschedule-requests).
 *  - Administrators and staff approve or disapprove a request, or reassign the date directly with a reason.
 */
class RescheduleRequestController extends Controller
{
    private const WITH = [
        'trip.allocation',
        'trip.vehicle',
        'trip.driver',
        'trip.vehicleRequest',
        'requester',
        'handler',
    ];

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
        $oldEndDate = $vehicleRequest->trip_end_date?->toDateString() ?? $oldDate;
        [, $newEndDate] = $this->allocations->tripRange($vehicleRequest, $newDate);

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
            'old_trip_end_date' => $oldEndDate,
            'new_trip_end_date' => $newEndDate,
            'old_trip_type' => $vehicleRequest->trip_type,
            'new_trip_type' => $vehicleRequest->trip_type,
            'old_authorized_passengers' => $vehicleRequest->passengers,
            'new_authorized_passengers' => $vehicleRequest->passengers,
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
     * Applies the requested trip changes after checking the new schedule and assignment.
     * The reviewer's note is optional.
     */
    public function approve(Request $request, RescheduleRequest $rescheduleRequest)
    {
        $validated = $request->validate([
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'admin_reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $rescheduleRequest, $validated) {
            $rescheduleRequest = RescheduleRequest::whereKey($rescheduleRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertPending($rescheduleRequest);

            $trip = $rescheduleRequest->trip()->lockForUpdate()->first();
            $vehicleRequest = $trip->vehicleRequest()->lockForUpdate()->first();
            $allocation = $trip->allocation()->lockForUpdate()->first();

            if ($trip->trip_status !== 'scheduled') {
                throw ValidationException::withMessages([
                    'new_date' => "Only scheduled trips can be modified (this trip is {$trip->trip_status}).",
                ]);
            }

            if (! $allocation) {
                throw ValidationException::withMessages([
                    'allocation' => 'This trip has no allocation to update.',
                ]);
            }

            $oldDate = $trip->trip_date->toDateString();
            $newDate = $rescheduleRequest->new_date?->toDateString() ?? $oldDate;
            $oldEndDate = $vehicleRequest->trip_end_date?->toDateString() ?? $oldDate;
            $newEndDate = $rescheduleRequest->new_trip_end_date?->toDateString()
                ?? $this->allocations->tripRange($vehicleRequest, $newDate)[1];
            $newDeparture = substr((string) ($rescheduleRequest->new_departure_time ?? $trip->departure_time), 0, 5);
            $newReturn = $rescheduleRequest->new_estimated_return_time !== null
                ? substr((string) $rescheduleRequest->new_estimated_return_time, 0, 5)
                : ($trip->estimated_return_time ? substr((string) $trip->estimated_return_time, 0, 5) : null);
            $newDestination = $rescheduleRequest->new_destination ?? $trip->destination;
            $newPurpose = $rescheduleRequest->new_purpose ?? $trip->purpose;
            $newPassengers = $rescheduleRequest->new_number_of_passengers ?? $vehicleRequest->number_of_passengers;
            $newTripType = $rescheduleRequest->new_trip_type ?? $vehicleRequest->trip_type;
            $newAuthorizedPassengers = $rescheduleRequest->new_authorized_passengers ?? $vehicleRequest->passengers;
            $vehicle = Vehicle::findOrFail($validated['vehicle_id'] ?? $trip->vehicle_id);
            $driver = Driver::findOrFail($validated['driver_id'] ?? $trip->driver_id);

            $vehicleRequest->departure_time = $newDeparture;
            $vehicleRequest->estimated_return_time = $newReturn;
            $this->allocations->assertAssignable(
                $vehicleRequest,
                $vehicle,
                $driver,
                $trip,
                $newDate,
                false,
                $newEndDate
            );

            if ($newDate !== $oldDate || $newEndDate !== $oldEndDate) {
                $this->allocations->shiftTripDate($trip, $newDate, $newEndDate);
            }

            $oldVehicleId = (int) $trip->vehicle_id;
            $oldDriverId = (int) $trip->driver_id;
            $vehicleChanged = $oldVehicleId !== (int) $vehicle->id;
            $driverChanged = $oldDriverId !== (int) $driver->id;

            $trip->update([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'departure_time' => $newDeparture,
                'estimated_return_time' => $newReturn,
                'destination' => $newDestination,
                'purpose' => $newPurpose,
            ]);
            $vehicleRequest->update([
                'departure_time' => $newDeparture,
                'estimated_return_time' => $newReturn,
                'destination' => $newDestination,
                'purpose' => $newPurpose,
                'trip_type' => $newTripType,
                'passengers' => $newAuthorizedPassengers,
                'number_of_passengers' => $newPassengers,
            ]);
            $allocation->update([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
            ]);

            if ($vehicleChanged) {
                $oldVehicleStillAssigned = Trip::where('vehicle_id', $oldVehicleId)
                    ->whereIn('trip_status', AllocationService::ACTIVE)
                    ->where('id', '!=', $trip->id)
                    ->exists();
                if (! $oldVehicleStillAssigned) {
                    Vehicle::whereKey($oldVehicleId)
                        ->where('status', 'in_use')
                        ->update(['status' => 'available']);
                }
                Vehicle::whereKey($vehicle->id)->update(['status' => 'in_use']);
            }

            if ($driverChanged) {
                $oldDriverStillAssigned = Trip::where('driver_id', $oldDriverId)
                    ->whereIn('trip_status', AllocationService::ACTIVE)
                    ->where('id', '!=', $trip->id)
                    ->exists();
                if (! $oldDriverStillAssigned) {
                    Driver::whereKey($oldDriverId)->update([
                        'is_available' => true,
                        'assigned_vehicle_id' => null,
                    ]);
                }
                Driver::whereKey($driver->id)->update([
                    'is_available' => false,
                    'assigned_vehicle_id' => $vehicle->id,
                ]);
            } elseif ($vehicleChanged) {
                Driver::whereKey($driver->id)->update(['assigned_vehicle_id' => $vehicle->id]);
            }

            $rescheduleRequest->update([
                'status' => 'approved',
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
                'admin_reason' => $request->input('admin_reason'),
            ]);
        });

        return new RescheduleRequestResource($rescheduleRequest->fresh()->load(self::WITH));
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