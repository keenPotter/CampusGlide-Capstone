<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAllocationRequest;
use App\Http\Requests\UpdateAllocationRequest;
use App\Http\Resources\AllocationResource;
use App\Models\Allocation;
use App\Models\Driver;
use App\Models\RescheduleRequest;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use App\Services\AllocationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocationController extends Controller
{
    private const WITH = ['trip.vehicleRequest.requester', 'vehicle', 'driver', 'allocatedBy'];

    public function __construct(protected AllocationService $allocations)
    {
    }

    /**
     * GET /api/allocations
     * Administrators see everything. Faculty see only their own requests.
     * Query params: ?status=scheduled, ?per_page= (max 100, so the frontend stats are complete)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Allocation::with(self::WITH);

        if ($user->hasRole('faculty')) {
            $query->whereHas('trip.vehicleRequest', fn ($q) => $q->where('requester_id', $user->id));
        }

        if ($status = $request->query('status')) {
            $query->whereHas('trip', fn ($q) => $q->where('trip_status', $status));
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return AllocationResource::collection(
            $query->latest('allocation_date')->paginate($perPage)
        );
    }

    /**
     * GET /api/allocations/{allocation}
     */
    public function show(Request $request, Allocation $allocation)
    {
        $user = $request->user();
        $allocation->load(self::WITH);

        if (
            $user->hasRole('faculty')
            && (int) $allocation->trip?->vehicleRequest?->requester_id !== (int) $user->id
        ) {
            abort(403, 'You may only view the trips of your own requests.');
        }

        return new AllocationResource($allocation);
    }

    /**
     * GET /api/allocation-options
     * Fills the dropdowns on the allocation page. Administrators only (route middleware).
     * - requests: approved requests that do not have a trip yet
     * - vehicles and drivers: every usable vehicle and every driver
     * Busy vehicles and drivers are not removed here. The conflict check runs on Allocate
     * and returns a 422 with "conflict" details for the conflict modal.
     */
    public function options(): JsonResponse
    {
        $requests = VehicleRequest::with('requester')
            ->where('status', 'approved')
            ->whereDoesntHave('trip')
            ->orderBy('trip_date')
            ->orderBy('departure_time')
            ->get()
            ->map(fn (VehicleRequest $r) => AllocationService::requestOption($r))
            ->values();

        $vehicles = Vehicle::where('is_active', true)
            ->whereIn('status', ['available', 'in_use'])
            ->orderBy('plate_number')
            ->get()
            ->map(fn (Vehicle $v) => AllocationService::vehicleOption($v))
            ->values();

        $drivers = Driver::orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Driver $d) => AllocationService::driverOption($d))
            ->values();

        return response()->json(compact('requests', 'vehicles', 'drivers'));
    }

    /**
     * POST /api/allocations
     * Creates the trip and the allocation record together.
     */
    public function store(StoreAllocationRequest $request)
    {
        $vehicleRequest = VehicleRequest::findOrFail($request->vehicle_request_id);
        $vehicle = Vehicle::findOrFail($request->vehicle_id);
        $driver = Driver::findOrFail($request->driver_id);

        $this->allocations->assertAssignable($vehicleRequest, $vehicle, $driver);

        $allocation = DB::transaction(function () use ($request, $vehicleRequest, $vehicle, $driver) {
            $trip = Trip::create([
                'vehicle_request_id' => $vehicleRequest->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'trip_date' => $vehicleRequest->trip_date,
                'departure_time' => $vehicleRequest->departure_time,
                'estimated_return_time' => $vehicleRequest->estimated_return_time,
                'destination' => $vehicleRequest->destination,
                'purpose' => $vehicleRequest->purpose,
                'trip_status' => 'scheduled',
            ]);

            $allocation = Allocation::create([
                'trip_id' => $trip->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'allocated_by' => $request->user()->id,
                'allocation_date' => now(),
                'notes' => $request->notes,
            ]);

            Vehicle::where('id', $vehicle->id)->update(['status' => 'in_use']);
            Driver::where('id', $driver->id)->update([
                'is_available' => false,
                'assigned_vehicle_id' => $vehicle->id,
            ]);

            return $allocation;
        });

        return (new AllocationResource($allocation->load(self::WITH)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/allocations/{allocation}
     * One call for everything on the Reassign window: vehicle, driver, trip date and trip status.
     * - A new date (new_date) needs a reason, and only a scheduled trip can be moved.
     * - The conflict check uses the new date together with the chosen vehicle and driver.
     * - A completed trip can no longer be changed, because that could free a
     *   vehicle or driver that another trip is already using.
     */
    public function update(UpdateAllocationRequest $request, Allocation $allocation)
    {
        $trip = $allocation->trip;

        $vehicle = $request->filled('vehicle_id') ? Vehicle::findOrFail($request->vehicle_id) : $allocation->vehicle;
        $driver = $request->filled('driver_id') ? Driver::findOrFail($request->driver_id) : $allocation->driver;

        $vehicleChanged = (int) $vehicle->id !== (int) $trip->vehicle_id;
        $driverChanged = (int) $driver->id !== (int) $trip->driver_id;
        $newStatus = $request->input('status', $trip->trip_status);
        $statusChanged = $newStatus !== $trip->trip_status;
        $wasActive = in_array($trip->trip_status, AllocationService::ACTIVE, true);

        $newDate = $request->input('new_date');
        $dateChanged = $newDate && $newDate !== Carbon::parse($trip->trip_date)->toDateString();

        if (! $wasActive && ($vehicleChanged || $driverChanged || $statusChanged || $dateChanged)) {
            throw ValidationException::withMessages([
                'status' => "This trip is already {$trip->trip_status} and can no longer be changed. Create a new request instead.",
            ]);
        }

        // Only a scheduled trip can be moved to another date.
        if ($dateChanged && $trip->trip_status !== 'scheduled') {
            throw ValidationException::withMessages([
                'new_date' => "Only scheduled trips can have their date changed (this trip is {$trip->trip_status}).",
            ]);
        }

        if ($wasActive && ($vehicleChanged || $driverChanged || $dateChanged)) {
            $this->allocations->assertAssignable(
                $trip->vehicleRequest,
                $vehicle,
                $driver,
                $trip,
                $dateChanged ? $newDate : null
            );
        }

        DB::transaction(function () use ($request, $allocation, $trip, $vehicle, $driver, $vehicleChanged, $driverChanged, $newStatus, $dateChanged, $newDate) {
            if ($vehicleChanged) {
                Vehicle::where('id', $trip->vehicle_id)->update(['status' => 'available']);
                Vehicle::where('id', $vehicle->id)->update(['status' => 'in_use']);
            }

            if ($driverChanged) {
                Driver::where('id', $trip->driver_id)->update(['is_available' => true]);
                Driver::where('id', $driver->id)->update([
                    'is_available' => false,
                    'assigned_vehicle_id' => $vehicle->id,
                ]);
            } elseif ($vehicleChanged) {
                Driver::where('id', $driver->id)->update(['assigned_vehicle_id' => $vehicle->id]);
            }

            Trip::where('id', $trip->id)->update([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'trip_status' => $newStatus,
            ]);

            $allocation->update([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'notes' => $request->input('notes', $allocation->notes),
            ]);

            // The new date: move the trip and its request, and keep a record with the reason.
            if ($dateChanged) {
                $oldDate = $this->allocations->shiftTripDate($trip, $newDate);

                // A pending faculty request is no longer needed once the date is changed directly.
                RescheduleRequest::where('trip_id', $trip->id)->where('status', 'pending')->delete();

                RescheduleRequest::create([
                    'trip_id' => $trip->id,
                    'requested_by' => $request->user()->id,
                    'old_date' => $oldDate,
                    'new_date' => $newDate,
                    'reason' => $request->input('reason'),
                    'status' => 'approved',
                    'handled_by' => $request->user()->id,
                    'handled_at' => now(),
                ]);
            }

            // A finished trip frees its vehicle and driver.
            if ($newStatus === 'completed') {
                Vehicle::where('id', $vehicle->id)->update(['status' => 'available']);
                Driver::where('id', $driver->id)->update(['is_available' => true]);
            }
        });

        return new AllocationResource($allocation->fresh(self::WITH));
    }
}