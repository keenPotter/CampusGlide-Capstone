<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\AllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Read-only access to trips.
 * Trips are created and changed only through the allocation module
 * (AllocationController), so the vehicle, driver and allocation record stay in sync.
 */
class TripController extends Controller
{
    private const WITH = ['allocation', 'driver', 'vehicle', 'vehicleRequest.requester', 'cancelledBy'];

    public function __construct(protected AllocationService $allocations)
    {
    }

    /**
     * GET /api/trips
     * All trips. Administrators and staff only (route middleware).
     * Trips that already passed their return time are marked completed first.
     */
    public function index(Request $request)
    {
        $this->allocations->completeFinishedTrips();

        $request->validate([
            'trip_type' => ['nullable', 'in:inclusive,exclusive'],
            'status' => ['nullable', 'in:scheduled,in_progress,completed'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:1900,2200'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $query = Trip::with(self::WITH);
        if ($request->filled('status')) {
            $query->where('trip_status', $request->query('status'));
        } else {
            $query->whereIn('trip_status', AllocationService::ACTIVE);
        }

        if ($request->filled('trip_type')) {
            $query->whereHas('vehicleRequest', fn ($requestQuery) =>
                $requestQuery->where('trip_type', $request->query('trip_type'))
            );
        }

        if ($request->filled('month')) {
            $query->whereMonth('trip_date', $request->query('month'));
        }

        if ($request->filled('year')) {
            $query->whereYear('trip_date', $request->query('year'));
        }

        if ($request->filled('search')) {
            $pattern = '%' . trim($request->query('search')) . '%';
            $query->where(function ($searchQuery) use ($pattern) {
                $searchQuery
                    ->where('destination', 'like', $pattern)
                    ->orWhere('purpose', 'like', $pattern)
                    ->orWhereHas('vehicle', fn ($vehicleQuery) =>
                        $vehicleQuery->where('vehicle_model', 'like', $pattern)
                            ->orWhere('plate_number', 'like', $pattern)
                    )
                    ->orWhereHas('driver', fn ($driverQuery) =>
                        $driverQuery->where('first_name', 'like', $pattern)
                            ->orWhere('last_name', 'like', $pattern)
                    )
                    ->orWhereHas('vehicleRequest.requester', fn ($requesterQuery) =>
                        $requesterQuery->where('first_name', 'like', $pattern)
                            ->orWhere('last_name', 'like', $pattern)
                    )
                    ->orWhereHas('vehicleRequest', fn ($requestQuery) =>
                        $requestQuery->where('requester_name', 'like', $pattern)
                            ->orWhere('requester_department', 'like', $pattern)
                            ->orWhere('requester_contact', 'like', $pattern)
                    );
            });
        }

        if ($request->filled('per_page')) {
            return TripResource::collection(
                $query->orderBy('trip_date')
                    ->orderBy('departure_time')
                    ->paginate((int) $request->query('per_page'))
                    ->appends($request->query())
            );
        }

        $trips = $query
            ->orderBy('trip_date')
            ->orderBy('departure_time')
            ->get();

        return TripResource::collection($trips);
    }

    /**
     * GET /api/my-trips
     * Trips of the logged-in faculty member only.
     * Filtered through the vehicle request's requester_id, so faculty
     * cannot see the trips of other users.
     */
    public function myTrips(Request $request)
    {
        $this->allocations->completeFinishedTrips();

        $request->validate([
            'status' => ['nullable', 'in:scheduled,in_progress,completed'],
            'trip_type' => ['nullable', 'in:inclusive,exclusive'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:1900,2200'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Trip::with(self::WITH)
            ->whereHas('vehicleRequest', fn ($query) => $query->where('requester_id', $request->user()->id))
            ->whereIn('trip_status', ['scheduled', 'in_progress', 'completed']);

        if ($request->filled('status')) {
            $query->where('trip_status', $request->query('status'));
        }

        if ($request->filled('trip_type')) {
            $query->whereHas('vehicleRequest', fn ($requestQuery) =>
                $requestQuery->where('trip_type', $request->query('trip_type'))
            );
        }

        if ($request->filled('month')) {
            $query->whereMonth('trip_date', $request->query('month'));
        }

        if ($request->filled('year')) {
            $query->whereYear('trip_date', $request->query('year'));
        }

        if ($request->filled('search')) {
            $pattern = '%' . trim($request->query('search')) . '%';
            $query->where(function ($searchQuery) use ($pattern) {
                $searchQuery
                    ->where('destination', 'like', $pattern)
                    ->orWhere('purpose', 'like', $pattern)
                    ->orWhereHas('vehicle', fn ($vehicleQuery) =>
                        $vehicleQuery->where('vehicle_model', 'like', $pattern)
                            ->orWhere('plate_number', 'like', $pattern)
                    )
                    ->orWhereHas('driver', fn ($driverQuery) =>
                        $driverQuery->where('first_name', 'like', $pattern)
                            ->orWhere('last_name', 'like', $pattern)
                    );
            });
        }

        return TripResource::collection(
            $query
            ->orderBy('trip_date')
            ->orderBy('departure_time')
                ->paginate(10)
                ->appends($request->query())
        );
    }

    /**
     * GET /api/trips/{trip}
     */
    public function edit(Request $request, Trip $trip)
    {
        $user = $request->user();
        $vehicleRequest = $trip->vehicleRequest;

        abort_unless(
            (int) $vehicleRequest?->requester_id === (int) $user->id,
            403,
            'You may only edit your own trips.'
        );
        abort_if(
            in_array($trip->trip_status, ['in_progress', 'completed'], true)
                || $vehicleRequest?->status === 'approved',
            422,
            'This trip can no longer be edited directly. Use Date Change Request instead.'
        );

        $validated = $request->validate([
            'trip_date' => ['nullable', 'date_format:Y-m-d'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'time' => ['nullable', 'date_format:H:i'],
            'estimated_return_time' => ['nullable', 'date_format:H:i'],
            'destination' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'number_of_passengers' => ['nullable', 'integer', 'min:1'],
            'passengers' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $trip, $vehicleRequest, $validated) {
            $tripUpdates = array_filter([
                'trip_date' => $validated['trip_date'] ?? $validated['date'] ?? null,
                'departure_time' => $validated['departure_time'] ?? $validated['time'] ?? null,
                'estimated_return_time' => $validated['estimated_return_time'] ?? null,
                'destination' => $validated['destination'] ?? null,
                'purpose' => $validated['purpose'] ?? null,
            ], fn ($value) => $value !== null);

            if ($tripUpdates) {
                $trip->update($tripUpdates);
                $vehicleRequestUpdates = [];
                if (isset($tripUpdates['trip_date'])) {
                    $vehicleRequestUpdates['trip_date'] = $tripUpdates['trip_date'];
                }
                if (isset($tripUpdates['departure_time'])) {
                    $vehicleRequestUpdates['departure_time'] = $tripUpdates['departure_time'];
                }
                if (isset($tripUpdates['estimated_return_time'])) {
                    $vehicleRequestUpdates['estimated_return_time'] = $tripUpdates['estimated_return_time'];
                }
                if (isset($tripUpdates['destination'])) {
                    $vehicleRequestUpdates['destination'] = $tripUpdates['destination'];
                }
                if (isset($tripUpdates['purpose'])) {
                    $vehicleRequestUpdates['purpose'] = $tripUpdates['purpose'];
                }
                if ($vehicleRequestUpdates) {
                    $vehicleRequest->update($vehicleRequestUpdates);
                }
            }

            $passengerCount = $validated['number_of_passengers'] ?? $validated['passengers'] ?? null;
            if ($passengerCount !== null) {
                $vehicleRequest->update([
                    'number_of_passengers' => $passengerCount,
                ]);
            }
        });

        return new TripResource($trip->fresh(self::WITH));
    }

    public function show(Trip $trip)
    {
        return new TripResource($trip->load(self::WITH));
    }
}