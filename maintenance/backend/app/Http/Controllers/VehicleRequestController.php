<?php

namespace App\Http\Controllers;

use App\Http\Requests\EditVehicleRequestRequest;
use App\Http\Requests\StoreVehicleRequestRequest;
use App\Http\Requests\UpdateVehicleRequestStatusRequest;
use App\Http\Resources\VehicleRequestResource;
use App\Models\VehicleRequest;
use Illuminate\Http\Request;

class VehicleRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = VehicleRequest::with('requester')->latest();

        if ($user->hasRole('faculty')) {
            $query->where('requester_id', $user->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return VehicleRequestResource::collection($query->paginate(20));
    }

    public function store(StoreVehicleRequestRequest $request)
    {
        $vehicleRequest = VehicleRequest::create([
            'requester_id' => $request->user()->id,
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

    public function show(Request $request, VehicleRequest $vehicleRequest)
    {
        $user = $request->user();

        if ($user->hasRole('faculty') && $vehicleRequest->requester_id !== $user->id) {
            abort(403, 'You are not authorized to view this request.');
        }

        return new VehicleRequestResource($vehicleRequest->load('requester'));
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

    public function edit(EditVehicleRequestRequest $request, VehicleRequest $vehicleRequest)
    {
        $validated = $request->validated();

        if ($vehicleRequest->status === 'approved') {
            $validated['status'] = 'pending';
            $validated['approved_by'] = null;
            $validated['approved_date'] = null;
        }

        $vehicleRequest->update($validated);

        return response()->json([
            'message' => 'Vehicle request updated successfully.',
            'data' => new VehicleRequestResource($vehicleRequest->fresh(['requester', 'approver'])),
        ]);
    }
}