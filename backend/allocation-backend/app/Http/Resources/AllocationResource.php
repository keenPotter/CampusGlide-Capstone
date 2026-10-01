<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $trip = $this->trip;
        $requester = $trip?->vehicleRequest?->requester;
        $driverUser = $this->driver?->user;

        return [
            'id' => $this->id,
            'status' => $trip?->trip_status,
            'trip' => [
                'id' => $trip?->id,
                'trip_date' => optional($trip?->trip_date)->toDateString(),
                'departure_time' => $trip?->departure_time,
                'estimated_return_time' => $trip?->estimated_return_time,
                'destination' => $trip?->destination,
                'purpose' => $trip?->purpose,
            ],
            'vehicle_request' => [
                'id' => $trip?->vehicleRequest?->id,
                'requester' => $requester ? trim($requester->first_name . ' ' . $requester->last_name) : null,
            ],
            'vehicle' => [
                'id' => $this->vehicle?->id,
                'plate_number' => $this->vehicle?->plate_number,
                'vehicle_model' => $this->vehicle?->vehicle_model,
            ],
            'driver' => [
                'id' => $this->driver?->id,
                'name' => $driverUser ? trim($driverUser->first_name . ' ' . $driverUser->last_name) : null,
                'license_number' => $this->driver?->license_number,
            ],
            'allocated_by' => $this->allocatedBy
                ? trim($this->allocatedBy->first_name . ' ' . $this->allocatedBy->last_name)
                : null,
            'allocation_date' => $this->allocation_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
