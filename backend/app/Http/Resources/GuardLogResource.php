<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuardLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_request_id' => $this->vehicle_request_id,
            'destination' => $this->vehicleRequest?->destination,
            'vehicle_used' => $this->vehicle_used,
            'guard' => [
                'id' => $this->guardUser->id,
                'first_name' => $this->guardUser->first_name,
                'last_name' => $this->guardUser->last_name,
            ],
            'actual_departure_date' => $this->actual_departure_date?->format('Y-m-d'),
            'actual_departure_time' => $this->actual_departure_time,
            'actual_return_date' => $this->actual_return_date?->format('Y-m-d'),
            'actual_return_time' => $this->actual_return_time,
            'vehicle_condition_departure' => $this->vehicle_condition_departure,
            'vehicle_condition_return' => $this->vehicle_condition_return,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}