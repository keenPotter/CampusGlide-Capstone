<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\VehicleRequest;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index()
    {
        return Trip::with([
            'driver',
            'vehicle',
            'vehicleRequest.requester',
        ])->get();
    }

    public function show($id)
    {
        $trip = Trip::with([
            'driver',
            'vehicle',
            'vehicleRequest.requester',
        ])->find($id);

        if (!$trip) {
            return response()->json([
                'message' => 'Trip not found.'
            ], 404);
        }

        return response()->json($trip);
    }

    public function update(Request $request, $id)
    {
        $trip = Trip::find($id);

        if (!$trip) {
            return response()->json([
                'message' => 'Trip not found.'
            ], 404);
        }

        $validated = $request->validate([
            'vehicle_id' => 'sometimes|integer|exists:vehicles,id',
            'driver_id' => 'sometimes|integer|exists:drivers,id',
            'trip_status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
        ]);

        if (isset($validated['trip_status']) && $validated['trip_status'] === 'cancelled') {
            $trip->update(['trip_status' => 'cancelled']);

            return response()->json($trip->fresh([
                'driver',
                'vehicle',
                'vehicleRequest.requester',
            ]));
        }

        $vehicleId = $validated['vehicle_id'] ?? $trip->vehicle_id;
        $driverId = $validated['driver_id'] ?? $trip->driver_id;

        $conflict = $this->findAssignmentConflict(
            $trip->trip_date,
            $trip->departure_time,
            $trip->estimated_return_time,
            $vehicleId,
            $driverId,
            $trip->id
        );

        if ($conflict) {
            return response()->json([
                'message' => $conflict
            ], 422);
        }

        $trip->update([
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
        ]);

        return response()->json($trip->fresh([
            'driver',
            'vehicle',
            'vehicleRequest.requester',
        ]));
    }

    public function destroy($id)
    {
        $trip = Trip::find($id);

        if (!$trip) {
            return response()->json([
                'message' => 'Trip not found.'
            ], 404);
        }

        $trip->delete();

        return response()->json([
            'message' => 'Trip deleted successfully.'
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_request_id' => 'required|exists:vehicle_requests,id',
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'driver_id' => 'required|integer|exists:drivers,id',
        ]);

        $vehicleRequest = VehicleRequest::with('trip')->find($validated['vehicle_request_id']);

        if (!$vehicleRequest || $vehicleRequest->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved vehicle requests can be scheduled.'
            ], 422);
        }

        if ($vehicleRequest->trip) {
            return response()->json([
                'message' => 'This approved vehicle request already has a scheduled trip.'
            ], 422);
        }

        $conflict = $this->findAssignmentConflict(
            $vehicleRequest->trip_date,
            $vehicleRequest->departure_time,
            $vehicleRequest->estimated_return_time,
            $validated['vehicle_id'],
            $validated['driver_id']
        );

        if ($conflict) {
            return response()->json([
                'message' => $conflict
            ], 422);
        }

        $trip = Trip::create([
            'vehicle_request_id' => $vehicleRequest->id,
            'vehicle_id' => $validated['vehicle_id'],
            'driver_id' => $validated['driver_id'],
            'trip_date' => $vehicleRequest->trip_date,
            'departure_time' => $vehicleRequest->departure_time,
            'estimated_return_time' => $vehicleRequest->estimated_return_time,
            'destination' => $vehicleRequest->destination,
            'purpose' => $vehicleRequest->purpose,
            'trip_status' => 'scheduled',
        ]);

        return response()->json($trip->load([
            'driver',
            'vehicle',
            'vehicleRequest.requester',
        ]), 201);
    }

    private function findAssignmentConflict(
        $tripDate,
        $departureTime,
        $estimatedReturnTime,
        $vehicleId,
        $driverId,
        $ignoreTripId = null
    ) {
        $trips = Trip::whereDate('trip_date', $tripDate)
            ->whereIn('trip_status', ['scheduled', 'in_progress'])
            ->when($ignoreTripId, function ($query) use ($ignoreTripId) {
                $query->where('id', '!=', $ignoreTripId);
            })
            ->get();

        $start = substr((string) $departureTime, 0, 5);
        $end = substr((string) ($estimatedReturnTime ?: '23:59:59'), 0, 5);

        foreach ($trips as $existingTrip) {
            $existingStart = substr((string) $existingTrip->departure_time, 0, 5);
            $existingEnd = substr((string) ($existingTrip->estimated_return_time ?: '23:59:59'), 0, 5);

            if ($start < $existingEnd && $end > $existingStart) {
                if ((int) $existingTrip->vehicle_id === (int) $vehicleId) {
                    return 'The selected vehicle is already scheduled during this trip time.';
                }

                if ((int) $existingTrip->driver_id === (int) $driverId) {
                    return 'The selected driver is already scheduled during this trip time.';
                }
            }
        }

        return null;
    }
}