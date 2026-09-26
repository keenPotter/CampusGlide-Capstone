<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuardLogRequest;
use App\Http\Requests\UpdateGuardLogReturnRequest;
use App\Http\Resources\GuardLogResource;
use App\Models\GuardLog;
use Illuminate\Http\Request;

class GuardLogController extends Controller
{
    /**
     * List guard logs. Guards see their own logs; administrators see all.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = GuardLog::with(['vehicleRequest', 'guardUser'])->latest();

        if ($user->hasRole('guard')) {
            $query->where('guard_id', $user->id);
        }

        return GuardLogResource::collection($query->paginate(20));
    }

    public function show(Request $request, GuardLog $guardLog)
    {
        $user = $request->user();

        if ($user->hasRole('guard') && $guardLog->guard_id !== $user->id) {
            abort(403, 'You are not authorized to view this log.');
        }

        return new GuardLogResource($guardLog->load(['vehicleRequest', 'guardUser']));
    }

    /**
     * Guard records that a vehicle has departed for an approved request.
     */
    public function store(StoreGuardLogRequest $request)
    {
        $guardLog = GuardLog::create([
            'vehicle_request_id' => $request->vehicle_request_id,
            'guard_id' => $request->user()->id,
            'vehicle_used' => $request->vehicle_used,
            'actual_departure_date' => $request->actual_departure_date,
            'actual_departure_time' => $request->actual_departure_time,
            'vehicle_condition_departure' => $request->vehicle_condition_departure,
            'remarks' => $request->remarks,
        ]);

        return new GuardLogResource($guardLog->load(['vehicleRequest', 'guardUser']));
    }

    /**
     * Guard records that the vehicle has returned.
     */
    public function recordReturn(UpdateGuardLogReturnRequest $request, GuardLog $guardLog)
    {
        if ($guardLog->guard_id !== $request->user()->id) {
            abort(403, 'Only the guard who logged the departure can log the return.');
        }

        $guardLog->update([
            'actual_return_date' => $request->actual_return_date,
            'actual_return_time' => $request->actual_return_time,
            'vehicle_condition_return' => $request->vehicle_condition_return,
            'remarks' => $request->remarks ?? $guardLog->remarks,
        ]);

        return new GuardLogResource($guardLog->fresh(['vehicleRequest', 'guardUser']));
    }
}