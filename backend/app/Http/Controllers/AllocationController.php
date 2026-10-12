<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAllocationRequest;
use App\Http\Requests\UpdateAllocationRequest;
use App\Http\Resources\AllocationResource;
use App\Models\Allocation;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use App\Services\AllocationService;
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
     * Reassigns the vehicle or driver, or changes the trip status.
     * A completed trip can no longer be changed, because that could free a
     * vehicle or driver that another trip is already using.
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

        if (! $wasActive && ($vehicleChanged || $driverChanged || $statusChanged)) {
            throw ValidationException::withMessages([
                'status' => "This trip is already {$trip->trip_status} and can no longer be changed. Create a new request instead.",
            ]);
        }

        if ($wasActive && ($vehicleChanged || $driverChanged)) {
            $this->allocations->assertAssignable($trip->vehicleRequest, $vehicle, $driver, $trip);
        }

        DB::transaction(function () use ($request, $allocation, $trip, $vehicle, $driver, $vehicleChanged, $driverChanged, $newStatus) {
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

            // A finished trip frees its vehicle and driver.
            if ($newStatus === 'completed') {
                Vehicle::where('id', $vehicle->id)->update(['status' => 'available']);
                Driver::where('id', $driver->id)->update(['is_available' => true]);
            }
        });

        return new AllocationResource($allocation->fresh(self::WITH));
    }
}