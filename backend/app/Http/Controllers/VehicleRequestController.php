<?php

namespace App\Http\Controllers;

use App\Http\Requests\EditVehicleRequestRequest;
use App\Http\Requests\StoreOnSiteVehicleRequestRequest;
use App\Http\Requests\StoreVehicleRequestRequest;
use App\Http\Requests\UpdateVehicleRequestStatusRequest;
use App\Models\Allocation;
use App\Http\Resources\VehicleRequestResource;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use App\Models\RescheduleRequest;
use App\Services\AllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleRequestController extends Controller
{
    public function __construct(protected AllocationService $allocations)
    {
    }

    /**
     * GET /api/vehicle-requests
     * Administrators see all. Faculty see only their own.
        * Query params: ?status=pending, ?trip_type=inclusive, ?month=1, ?year=2026
     *
     * Requests are grouped by status, then ordered by travel date and departure time.
     */
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:1900,2200'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $this->allocations->completeFinishedTrips();
        $user = $request->user();

        $query = VehicleRequest::query()
            ->select('vehicle_requests.*')
            ->with(['requester', 'trip'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'disapproved' THEN 2 ELSE 3 END")
            ->orderBy('trip_date')
            ->orderBy('departure_time')
            ->orderBy('id');

        if ($user->hasRole('faculty')) {
            $query->where('requester_id', $user->id);
        }

        if ($status = $request->query('status')) {
            if ($status === 'completed') {
                $query->where('status', 'approved')
                    ->whereHas('trip', fn ($tripQuery) => $tripQuery->where('trip_status', 'completed'));
            } else {
                $query->where('status', $status);
            }
        }

        // The frontend sends trip_type, so the backend must filter by it too.
        if ($tripType = $request->query('trip_type')) {
            $query->where('trip_type', $tripType);
        }

        if ($request->filled('year')) {
            $query->whereYear('trip_date', $request->query('year'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('trip_date', $request->query('month'));
        }

        if ($request->filled('search')) {
            $terms = preg_split('/\s+/', trim($request->query('search')));
            $query->where(function ($searchQuery) use ($terms) {
                foreach ($terms as $term) {
                    $searchQuery->where(function ($termQuery) use ($term) {
                        $pattern = "%{$term}%";
                        $termQuery
                            ->where('destination', 'like', $pattern)
                            ->orWhere('purpose', 'like', $pattern)
                            ->orWhere('disapproval_reason', 'like', $pattern)
                            ->orWhere('requester_name', 'like', $pattern)
                            ->orWhere('requester_department', 'like', $pattern)
                            ->orWhere('requester_contact', 'like', $pattern)
                            ->orWhereHas('requester', function ($requesterQuery) use ($pattern) {
                                $requesterQuery
                                    ->where('first_name', 'like', $pattern)
                                    ->orWhere('last_name', 'like', $pattern)
                                    ->orWhere('email', 'like', $pattern);
                            });
                    });
                }
            });
        }

        $perPage = $request->integer('per_page', 10);

        return VehicleRequestResource::collection(
            $query->paginate($perPage)->appends($request->query())
        );
    }

    /**
     * GET /api/vehicle-requests/approved
     * Approved requests that do not have a trip yet. Administrators only (route middleware).
     */
    public function approved()
    {
        $requests = VehicleRequest::with('requester')
            ->where('status', 'approved')
            ->whereDoesntHave('trip')
            ->orderBy('trip_date')
            ->orderBy('departure_time')
            ->get();

        return VehicleRequestResource::collection($requests);
    }

    public function store(StoreVehicleRequestRequest $request)
    {
        $vehicleRequest = VehicleRequest::create([
            'requester_id' => $request->user()->id,
            'booking_source' => VehicleRequest::SOURCE_ONLINE,
            'request_date' => now(),
            'destination' => $request->destination,
            'purpose' => $request->purpose,
            'trip_date' => $request->trip_date,
            'trip_end_date' => $request->trip_end_date,
            'trip_type' => $request->trip_type,
            'departure_time' => $request->departure_time,
            'estimated_return_time' => $request->estimated_return_time,
            'passengers' => $request->passengers,
            'number_of_passengers' => $request->number_of_passengers,
            'status' => 'pending',
        ]);

        return new VehicleRequestResource($vehicleRequest->load('requester'));
    }

    public function storeOnSite(StoreOnSiteVehicleRequestRequest $request)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data, $request) {
            $vehicleRequest = VehicleRequest::create([
                'requester_id' => null,
                'booking_source' => VehicleRequest::SOURCE_ON_SITE,
                'requester_name' => $data['requester_name'],
                'requester_department' => $data['requester_department'],
                'requester_contact' => $data['requester_contact'],
                'request_date' => now(),
                'destination' => $data['destination'],
                'purpose' => $data['purpose'],
                'trip_date' => $data['trip_date'],
                'trip_end_date' => $data['trip_end_date'],
                'trip_type' => $data['trip_type'],
                'departure_time' => $data['departure_time'],
                'estimated_return_time' => $data['estimated_return_time'],
                'passengers' => $data['passengers'],
                'number_of_passengers' => $data['number_of_passengers'],
                'status' => 'pending',
            ]);

            if (! empty($data['vehicle_id']) && ! empty($data['driver_id'])) {
                $this->allocations->allocateRequest(
                    $vehicleRequest,
                    Vehicle::findOrFail($data['vehicle_id']),
                    Driver::findOrFail($data['driver_id']),
                    (int) $request->user()->id,
                    approvePending: true
                );
            }

            return (new VehicleRequestResource($vehicleRequest->fresh('requester')))
                ->response()
                ->setStatusCode(201);
        });
    }

    public function show(Request $request, VehicleRequest $vehicleRequest)
    {
        $user = $request->user();

        if ($user->hasRole('faculty') && $vehicleRequest->requester_id !== $user->id) {
            abort(403, 'You are not authorized to view this request.');
        }

        $this->allocations->completeFinishedTrips();

        return new VehicleRequestResource($vehicleRequest->load(['requester', 'trip']));
    }

    public function updateStatus(UpdateVehicleRequestStatusRequest $request, VehicleRequest $vehicleRequest)
    {
        $isApproved = $request->status === 'approved';

        $vehicleRequest->update([
            'status' => $request->status,
            'disapproval_reason' => $isApproved ? null : $request->remarks,
            'approved_by' => $request->user()->id,
            'approved_date' => $isApproved ? now() : null,
        ]);

        return new VehicleRequestResource($vehicleRequest->fresh('requester'));
    }

    /**
     * PUT /api/vehicle-requests/{id}/edit
     * Faculty edit their own request.
     * Editing an approved request (a reason is required) creates a pending Date Change Request.
     * The existing trip and its assignment stay in place until the date change is approved.
     */
    public function edit(EditVehicleRequestRequest $request, VehicleRequest $vehicleRequest)
    {
        $validated = $request->validated();
        $wasApproved = $vehicleRequest->status === 'approved';
        $trip = $vehicleRequest->trip;
        $user = $request->user();

        abort_unless(
            (int) $vehicleRequest->requester_id === (int) $user->id,
            403,
            'You may only edit your own vehicle requests.'
        );

        // A trip that already started or finished cannot be undone by editing the request.
        abort_if(
            $trip && $trip->trip_status !== 'scheduled',
            422,
            "This request can no longer be edited because its trip is already {$trip?->trip_status}."
        );

        if ($wasApproved) {
            abort_unless($trip, 422, 'An approved request with no trip cannot create a date change.');
            abort_if(
                in_array($trip->trip_status, ['in_progress', 'completed'], true),
                422,
                "This trip is already {$trip->trip_status} and cannot be modified."
            );
            abort_if(
                RescheduleRequest::where('trip_id', $trip->id)->where('status', 'pending')->exists(),
                422,
                'This trip already has a pending Date Change Request.'
            );

            $oldDate = $trip->trip_date->toDateString();
            $newDate = $validated['trip_date'] ?? $oldDate;
            $oldEndDate = $vehicleRequest->trip_end_date?->toDateString() ?? $oldDate;
            [, $newEndDate] = $this->allocations->tripRange($vehicleRequest, $newDate);
            $oldTripType = $vehicleRequest->trip_type;
            $newTripType = $oldTripType;
            $oldDeparture = substr((string) $trip->departure_time, 0, 5);
            $newDeparture = substr((string) ($validated['departure_time'] ?? $oldDeparture), 0, 5);
            $oldReturn = $trip->estimated_return_time ? substr((string) $trip->estimated_return_time, 0, 5) : null;
            $newReturn = $oldReturn;
            $oldPassengers = $vehicleRequest->number_of_passengers;
            $newPassengers = (int) ($validated['number_of_passengers'] ?? $oldPassengers);
            $oldAuthorizedPassengers = $vehicleRequest->passengers;
            $newAuthorizedPassengers = $oldAuthorizedPassengers;
            $oldDestination = $trip->destination;
            $newDestination = $oldDestination;
            $oldPurpose = $trip->purpose;
            $newPurpose = $oldPurpose;

            abort_if(
                $newDate === $oldDate
                    && $newDeparture === $oldDeparture,
                422,
                'No date or departure time was changed.'
            );

            RescheduleRequest::create([
                'trip_id' => $trip->id,
                'requested_by' => $user->id,
                'old_date' => $oldDate,
                'new_date' => $newDate,
                'old_trip_end_date' => $oldEndDate,
                'new_trip_end_date' => $newEndDate,
                'old_trip_type' => $oldTripType,
                'new_trip_type' => $newTripType,
                'old_departure_time' => $oldDeparture,
                'new_departure_time' => $newDeparture,
                'old_estimated_return_time' => $oldReturn,
                'new_estimated_return_time' => $newReturn,
                'old_destination' => $oldDestination,
                'new_destination' => $newDestination,
                'old_purpose' => $oldPurpose,
                'new_purpose' => $newPurpose,
                'old_number_of_passengers' => $oldPassengers,
                'new_number_of_passengers' => $newPassengers,
                'old_authorized_passengers' => $oldAuthorizedPassengers,
                'new_authorized_passengers' => $newAuthorizedPassengers,
                'reason' => $validated['edit_reason'],
                'status' => 'pending',
            ]);
        } else {
            DB::transaction(function () use ($vehicleRequest, $trip, $validated) {
                if ($trip) {
                    $this->releaseTrip($trip);
                }

                $vehicleRequest->update($validated);
            });
        }

        return response()->json([
            'message' => $wasApproved
                ? 'Date Change Request submitted successfully.'
                : 'Vehicle request updated successfully.',
            'data' => new VehicleRequestResource($vehicleRequest->fresh(['requester', 'approver'])),
        ]);
    }

    /**
     * Deletes a trip and frees its vehicle and driver, unless another active trip still uses them.
     * The allocation record, date change requests and shared copies are deleted with the trip.
     */
    private function releaseTrip(Trip $trip): void
    {
        $vehicleId = $trip->vehicle_id;
        $driverId = $trip->driver_id;

        $trip->delete();

        $stillUsed = fn (string $column, int $id) => Trip::where($column, $id)
            ->whereIn('trip_status', AllocationService::ACTIVE)
            ->exists();

        if (! $stillUsed('vehicle_id', $vehicleId)) {
            // Only an "in use" vehicle is reset, so a vehicle under maintenance keeps its status.
            Vehicle::where('id', $vehicleId)->where('status', 'in_use')->update(['status' => 'available']);
        }

        if (! $stillUsed('driver_id', $driverId)) {
            Driver::where('id', $driverId)->update(['is_available' => true, 'assigned_vehicle_id' => null]);
        }
    }
}