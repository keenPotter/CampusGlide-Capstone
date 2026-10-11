<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AllocationRequest;
use App\Http\Resources\AllocationResource;
use App\Models\Allocation;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
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
        // Relationships na kailangan sa Trip / VehicleRequest ng ibang modules.
        Allocation::registerSharedRelations();
    }

    /**
     * Admin: lahat. End user: sariling request/trip lang.
     * May ?per_page= (max 100) para tama ang stats sa frontend.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Allocation::with(self::WITH);

        if (! AllocationService::isAdmin($user)) {
            $this->limitToOwn($query, $user);
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

        abort_unless(
            AllocationService::isAdmin($user) || $this->isOwn($allocation, $user),
            403,
            'You may only view the trips of your own requests.'
        );

        $allocation->load(self::WITH);

        return (new AllocationResource($allocation))->response();
    }

    /**
     * Detalye para sa printable (filled) Trip Request. Admin lang (ang Boss ay Admin din).
     * Walang Boss approval status/button — physical signature lang ang Boss (puwang sa papel).
     * GET /api/allocations/{allocation}/print
     */
    public function print(Request $request, Allocation $allocation): JsonResponse
    {
        abort_unless(AllocationService::isAdmin($request->user()), 403, 'Admin only.');

        $allocation->load(self::WITH);

        $vr = $allocation->trip?->vehicleRequest;
        $attrs = $vr ? $vr->getAttributes() : [];
        $approver = ! empty($attrs['approved_by']) ? User::find($attrs['approved_by']) : null;

        return response()->json(['data' => [
            'allocation' => (new AllocationResource($allocation))->resolve($request),
            'request' => [
                'id' => $vr?->id,
                'status' => $attrs['status'] ?? null,
                'requested_on' => $attrs['request_date'] ?? ($attrs['created_at'] ?? null),
                'passengers' => $attrs['number_of_passengers'] ?? null,
                'approved_by' => $approver
                    ? trim(($approver->first_name ?? '') . ' ' . ($approver->last_name ?? ''))
                    : null,
                'approved_on' => $attrs['approved_date'] ?? null,
            ],
        ]]);
    }

    /**
     * Pang-populate ng dropdowns sa frontend. Admin lang.
     * GET /api/allocation-options[?vehicle_request_id=ID]
     * - approved requests na wala pang trip + lahat ng active na vehicle at lahat ng driver
     * - ang conflict (busy sa oras na iyon) ay sinusuri sa Allocate, hindi dito
     * Ang bilang ng `requests` ay ginagamit din ng frontend bilang indicator
     * na "may approved request na kailangan ng vehicle at driver".
     */
    public function options(Request $request): JsonResponse
    {
        abort_unless(AllocationService::isAdmin($request->user()), 403, 'Admin only.');

        $requests = VehicleRequest::with('requester')
            ->where('status', 'approved')
            ->whereDoesntHave('trip')
            ->orderBy('trip_date')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => "#{$r->id} · {$r->destination} · "
                    . Carbon::parse($r->trip_date)->toDateString() . ' '
                    . substr((string) $r->departure_time, 0, 5),
                // Detalye para sa "Needs allocation" cards ng frontend.
                'destination' => $r->destination,
                'purpose' => $r->purpose,
                'requester' => $r->requester
                    ? trim(($r->requester->first_name ?? '') . ' ' . ($r->requester->last_name ?? ''))
                    : null,
                'trip_date' => Carbon::parse($r->trip_date)->toDateString(),
                'trip_end_date' => $r->trip_end_date?->toDateString(),
                'trip_type' => $r->trip_type,
                'departure_time' => $r->departure_time,
                'estimated_return_time' => $r->estimated_return_time,
                'passengers' => $r->passengers,
                'number_of_passengers' => $r->number_of_passengers,
            ])
            ->values();

        // Hindi na sinasala ang busy na vehicle/driver dito: ang conflict ay lalabas sa modal
        // (422 + "conflict" details) kapag pinindot ang Allocate button.
        // Ang ibang request-specific na tseke (license, availability) ay nasa AllocationService.
        $vehicles = Vehicle::where('is_active', true)
            ->whereIn('status', ['available', 'in_use'])
            ->get()
            ->map(fn ($v) => AllocationService::vehicleOption($v))
            ->values();

        $drivers = Driver::all()
            ->map(fn ($d) => AllocationService::driverOption($d))
            ->values();

        return response()->json(compact('requests', 'vehicles', 'drivers'));
    }

    /**
     * Gumagawa ng trips row + allocations row. Admin lang (tingnan ang AllocationRequest).
     */
    public function store(AllocationRequest $request): JsonResponse
    {
        $vehicleRequest = VehicleRequest::findOrFail($request->vehicle_request_id);
        $vehicle = Vehicle::findOrFail($request->vehicle_id);
        $driver = Driver::findOrFail($request->driver_id);

        $this->allocations->assertAssignable($vehicleRequest, $vehicle, $driver);

        $allocation = DB::transaction(function () use ($request, $vehicleRequest, $vehicle, $driver) {
            // forceCreate: hindi umaasa sa $fillable ng Trip model ng ibang module.
            $trip = Trip::forceCreate([
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
            Driver::where('id', $driver->id)->update(['is_available' => false, 'assigned_vehicle_id' => $vehicle->id]);

            return $allocation;
        });

        $allocation->load(self::WITH);

        return (new AllocationResource($allocation))->response()->setStatusCode(201);
    }

    /**
     * Reassign vehicle/driver o palitan ang status ng trip. Admin lang.
     * Ang completed/cancelled na trip ay hindi na pwedeng i-reassign o buksan ulit
     * (para hindi mag-"free" ng vehicle/driver na gamit na ng ibang trip).
     */
    public function update(AllocationRequest $request, Allocation $allocation): JsonResponse
    {
        $trip = $allocation->trip;

        $vehicle = $request->filled('vehicle_id') ? Vehicle::findOrFail($request->vehicle_id) : $allocation->vehicle;
        $driver = $request->filled('driver_id') ? Driver::findOrFail($request->driver_id) : $allocation->driver;

        $vehicleChanged = $vehicle->id !== $trip->vehicle_id;
        $driverChanged = $driver->id !== $trip->driver_id;
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
                Driver::where('id', $driver->id)->update(['is_available' => false, 'assigned_vehicle_id' => $vehicle->id]);
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

            if (in_array($newStatus, ['completed', 'cancelled'], true)) {
                Vehicle::where('id', $vehicle->id)->update(['status' => 'available']);
                Driver::where('id', $driver->id)->update(['is_available' => true]);
            }
        });

        $allocation->load(self::WITH);

        return (new AllocationResource($allocation))->response();
    }

    /** End user: sariling request lang ang makikita (siya ang requester ng trip). */
    private function limitToOwn($query, $user): void
    {
        $query->whereHas('trip.vehicleRequest', fn ($r) => $r->where('requester_id', $user->id));
    }

    private function isOwn(Allocation $allocation, $user): bool
    {
        $allocation->loadMissing('trip.vehicleRequest');

        return (int) $allocation->trip?->vehicleRequest?->requester_id === (int) $user->id;
    }
}
