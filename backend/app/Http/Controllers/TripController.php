<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Services\AllocationService;
use Illuminate\Http\Request;

/**
 * Read-only access to trips.
 * Trips are created and changed only through the allocation module
 * (AllocationController), so the vehicle, driver and allocation record stay in sync.
 */
class TripController extends Controller
{
    private const WITH = ['driver', 'vehicle', 'vehicleRequest.requester'];

    public function __construct(protected AllocationService $allocations)
    {
    }

    /**
     * GET /api/trips
     * All trips. Administrators only (route middleware).
     * Trips that already passed their return time are marked completed first.
     */
    public function index(Request $request)
    {
        $this->allocations->completeFinishedTrips();

        $query = Trip::with(self::WITH)
            ->whereIn('trip_status', AllocationService::ACTIVE);

        if ($request->filled('trip_type')) {
            $query->whereHas('vehicleRequest', fn ($requestQuery) =>
                $requestQuery->where('trip_type', $request->query('trip_type'))
            );
        }

        if ($request->filled('status')) {
            $query->where('trip_status', $request->query('status'));
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
                    );
            });
        }

        if ($request->filled('per_page')) {
            $request->validate([
                'trip_type' => ['nullable', 'in:inclusive,exclusive'],
                'status' => ['nullable', 'in:scheduled,in_progress'],
                'month' => ['nullable', 'integer', 'between:1,12'],
                'year' => ['nullable', 'integer', 'between:1900,2200'],
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['integer', 'between:1,100'],
            ]);

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
    public function show(Trip $trip)
    {
        return new TripResource($trip->load(self::WITH));
    }
}