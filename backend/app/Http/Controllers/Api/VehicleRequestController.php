<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleRequest;
use App\Services\AllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VehicleRequest::with('requester')->latest('id');

        if (! AllocationService::isAdmin($request->user())) {
            $query->where('requester_id', $request->user()->id);
        }

        if ($status = $request->query('status')) {
            if ($status === 'disapproved') {
                $query->whereIn('status', ['cancelled', 'disapproved', 'rejected']);
            } else {
                $query->where('status', $status);
            }
        }

        $requests = $query->limit(100)->get()->map(fn (VehicleRequest $vehicleRequest) => $this->shape($vehicleRequest));

        return response()->json(['data' => $requests->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trip_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'trip_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:trip_date'],
            'trip_type' => ['required', 'in:inclusive,exclusive'],
            'departure_time' => ['required', 'date_format:H:i'],
            'estimated_return_time' => ['required', 'date_format:H:i', 'after:departure_time'],
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'passengers' => ['required', 'string', 'max:255'],
            'number_of_passengers' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $vehicleRequest = VehicleRequest::create([
            ...$data,
            'requester_id' => $request->user()->id,
            'request_date' => now()->toDateString(),
            'status' => 'pending',
        ])->load('requester');

        return response()->json(['data' => $this->shape($vehicleRequest)], 201);
    }

    public function approve(Request $request, VehicleRequest $vehicleRequest): JsonResponse
    {
        abort_unless(AllocationService::isAdmin($request->user()), 403, 'Admin only.');

        $updated = VehicleRequest::query()
            ->whereKey($vehicleRequest->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_date' => now()->toDateString(),
            ]);

        if (! $updated) {
            return response()->json(['message' => 'Only pending vehicle requests can be approved.'], 422);
        }

        $vehicleRequest->refresh()->load('requester');

        return response()->json(['data' => $this->shape($vehicleRequest)]);
    }

    public function updateStatus(Request $request, VehicleRequest $vehicleRequest): JsonResponse
    {
        abort_unless(AllocationService::isAdmin($request->user()), 403, 'Admin only.');

        $data = $request->validate([
            'status' => ['required', 'in:approved,disapproved'],
            'remarks' => ['nullable', 'required_if:status,disapproved', 'string', 'max:500'],
        ], [
            'remarks.required_if' => 'Please provide a reason for disapproval.',
        ]);

        $status = $data['status'];
        $updated = VehicleRequest::query()
            ->whereKey($vehicleRequest->id)
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'disapproval_reason' => $status === 'disapproved' ? trim($data['remarks']) : null,
                'approved_by' => $request->user()->id,
                'approved_date' => $status === 'approved' ? now()->toDateString() : null,
            ]);

        if (! $updated) {
            return response()->json(['message' => 'Only pending vehicle requests can be updated.'], 422);
        }

        $vehicleRequest->refresh()->load('requester');

        return response()->json(['data' => $this->shape($vehicleRequest)]);
    }

    private function shape(VehicleRequest $vehicleRequest): array
    {
        $requester = $vehicleRequest->requester;

        return [
            'id' => $vehicleRequest->id,
            'requester' => $requester
                ? trim(($requester->first_name ?? '') . ' ' . ($requester->last_name ?? ''))
                : null,
            'request_date' => $vehicleRequest->request_date,
            'trip_date' => $vehicleRequest->trip_date?->toDateString(),
            'trip_end_date' => $vehicleRequest->trip_end_date?->toDateString(),
            'trip_type' => $vehicleRequest->trip_type,
            'departure_time' => $vehicleRequest->departure_time,
            'estimated_return_time' => $vehicleRequest->estimated_return_time,
            'destination' => $vehicleRequest->destination,
            'purpose' => $vehicleRequest->purpose,
            'passengers' => $vehicleRequest->passengers,
            'number_of_passengers' => $vehicleRequest->number_of_passengers,
            'status' => in_array($vehicleRequest->status, ['cancelled', 'rejected'], true)
                ? 'disapproved'
                : $vehicleRequest->status,
            'disapproval_reason' => $vehicleRequest->getAttribute('disapproval_reason'),
            'rejection_reason' => $vehicleRequest->getAttribute('disapproval_reason'),
        ];
    }
}
