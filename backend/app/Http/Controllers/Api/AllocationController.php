<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
    private const ACTIVE = ['scheduled', 'in_progress'];

    private const WITH = ['trip.vehicleRequest.requester', 'vehicle', 'driver.user', 'allocatedBy'];

    public function __construct(protected AllocationService $allocations)
    {
    }

    /**
     * Admin: lahat. Driver: sariling assignments lang.
     * FIXED: ibang roles (hal. requester) ay 403 na — dati nakikita nila lahat.
     * FIXED: may ?per_page= (max 100) para tama ang stats sa frontend.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['administrator', 'driver'], true), 403, 'Not allowed.');

        $query = Allocation::with(self::WITH);

        if ($user->role === 'driver') {
            $driver = Driver::where('user_id', $user->id)->first();
            $query->where('driver_id', $driver?->id ?? 0);
        }

        if ($status = $request->query('status')) {
            $query->whereHas('trip', fn ($q) => $q->where('trip_status', $status));
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        return AllocationResource::collection(
            $query->latest('allocation_date')->paginate($perPage)
        )->response();
    }

    public function show(Allocation $allocation, Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['administrator', 'driver'], true), 403, 'Not allowed.');

        if ($user->role === 'driver') {
            $driver = Driver::where('user_id', $user->id)->first();
            abort_unless($driver && $allocation->driver_id === $driver->id, 403, 'You may only view your own trip assignments.');
        }

        $allocation->load(self::WITH);

        return (new AllocationResource($allocation))->response();
    }

    /**
     * NEW — pang-populate ng dropdowns sa frontend (dati number-ID inputs lang).
     * GET /api/allocation-options[?vehicle_request_id=ID]
     * - walang ID: approved requests na wala pang trip + lahat ng ready na vehicle/driver
     * - may ID: vehicles/drivers na libre sa oras ng request na iyon
     */
    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'administrator', 403, 'Administrator only.');

        $requests = VehicleRequest::with('requester')
            ->where('status', 'approved')
            ->whereDoesntHave('trip')
            ->orderBy('trip_date')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => "#{$r->id} · {$r->destination} · "
                    . $r->trip_date->toDateString() . ' '
                    . substr((string) $r->departure_time, 0, 5),
            ])
            ->values();

        $vehicleQuery = Vehicle::where('is_active', true)->where('status', 'available');
        $driverQuery = Driver::with('user')
            ->where('is_available', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true));

        $vr = VehicleRequest::find((int) $request->query('vehicle_request_id'));

        if ($vr) {
            $date = $vr->trip_date->toDateString();
            $busy = fn () => Trip::active()->overlapping($date, $vr->departure_time, $vr->estimated_return_time);

            $vehicleQuery->whereNotIn('id', $busy()->pluck('vehicle_id')->all());
            $driverQuery->whereNotIn('id', $busy()->pluck('driver_id')->all())
                ->whereDate('license_expiry_date', '>=', $date);
        }

        $vehicles = $vehicleQuery->get()->map(function ($v) {
            $cap = $v->getAttribute('capacity');

            return [
                'id' => $v->id,
                'label' => "{$v->plate_number} — {$v->vehicle_model}" . ($cap ? " ({$cap} seats)" : ''),
            ];
        })->values();

        $drivers = $driverQuery->get()->map(fn ($d) => [
            'id' => $d->id,
            'label' => trim(($d->user->first_name ?? '') . ' ' . ($d->user->last_name ?? ''))
                . ($d->license_expiry_date ? ' (Lic. exp. ' . $d->license_expiry_date->format('M Y') . ')' : ''),
        ])->values();

        return response()->json(compact('requests', 'vehicles', 'drivers'));
    }

    /**
     * Gumagawa ng trips row + allocations row + notification sa driver.
     */
    public function store(StoreAllocationRequest $request): JsonResponse
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
            $driver->update(['is_available' => false, 'assigned_vehicle_id' => $vehicle->id]);

            return $allocation;
        });

        $allocation->load(self::WITH);
        $this->notifyDriver($driver, $allocation->trip, $request->user()->id, 'New Trip Assignment');

        return (new AllocationResource($allocation))->response()->setStatusCode(201);
    }

    /**
     * Reassign vehicle/driver o palitan ang status ng trip.
     * FIXED: hindi na laging 422 (tingnan ang AllocationService).
     * FIXED: completed/cancelled na trip ay hindi na pwedeng i-reassign o buksan ulit
     *        (para hindi mag-"free" ng vehicle/driver na gamit na ng ibang trip).
     */
    public function update(UpdateAllocationRequest $request, Allocation $allocation): JsonResponse
    {
        $trip = $allocation->trip;

        $vehicle = $request->filled('vehicle_id') ? Vehicle::findOrFail($request->vehicle_id) : $allocation->vehicle;
        $driver = $request->filled('driver_id') ? Driver::findOrFail($request->driver_id) : $allocation->driver;

        $vehicleChanged = $vehicle->id !== $trip->vehicle_id;
        $driverChanged = $driver->id !== $trip->driver_id;
        $newStatus = $request->input('status', $trip->trip_status);
        $statusChanged = $newStatus !== $trip->trip_status;
        $wasActive = in_array($trip->trip_status, self::ACTIVE, true);

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
                $driver->update(['is_available' => false, 'assigned_vehicle_id' => $vehicle->id]);
            } elseif ($vehicleChanged) {
                $driver->update(['assigned_vehicle_id' => $vehicle->id]);
            }

            $trip->update([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'trip_status' => $newStatus,
            ]);

            $allocation->update([
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'notes' => $request->input('notes', $allocation->notes),
            ]);

            if (in_array($newStatus, ['completed', 'cancelled'], true)) {
                Vehicle::where('id', $vehicle->id)->update(['status' => 'available']);
                Driver::where('id', $driver->id)->update(['is_available' => true]);
            }
        });

        $allocation->load(self::WITH);

        if ($driverChanged) {
            $this->notifyDriver($driver, $allocation->trip, $request->user()->id, 'Trip Reassigned to You');
        }

        return (new AllocationResource($allocation))->response();
    }

    /**
     * Cancel (soft): trip -> cancelled, vehicle/driver balik sa available.
     * FIXED: kapag cancelled/completed na, wala nang gagawin (para hindi ma-free
     * ang vehicle/driver na ginagamit na ng ibang trip).
     */
    public function destroy(Allocation $allocation): JsonResponse
    {
        $trip = $allocation->trip;

        if (! in_array($trip->trip_status, self::ACTIVE, true)) {
            return response()->json(['message' => "Trip is already {$trip->trip_status}."], 422);
        }

        DB::transaction(function () use ($allocation, $trip) {
            $trip->update(['trip_status' => 'cancelled']);
            Vehicle::where('id', $allocation->vehicle_id)->update(['status' => 'available']);
            Driver::where('id', $allocation->driver_id)->update(['is_available' => true]);
        });

        return response()->json(['message' => 'Allocation cancelled.']);
    }

    /**
     * Galing sa unang zip (web controller): notification sa driver.
     * Hindi nito ibabagsak ang allocation kung walang Notification model/table.
     */
    protected function notifyDriver(Driver $driver, Trip $trip, int $senderId, string $title): void
    {
        $class = \App\Models\Notification::class;

        if (! class_exists($class) || ! $driver->user_id) {
            return;
        }

        try {
            $class::create([
                'recipient_id' => $driver->user_id,
                'sender_id' => $senderId,
                'notification_type' => 'trip_assigned',
                'title' => $title,
                'message' => "You have been assigned to a trip to {$trip->destination} on {$trip->trip_date->format('M d, Y')}.",
                'related_entity_type' => 'trip',
                'related_entity_id' => $trip->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
