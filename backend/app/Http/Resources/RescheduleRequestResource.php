<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescheduleRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $trip = $this->trip;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'old_date' => $this->old_date?->toDateString(),
            'new_date' => $this->new_date?->toDateString(),
            'old_departure_time' => $this->old_departure_time,
            'new_departure_time' => $this->new_departure_time,
            'old_estimated_return_time' => $this->old_estimated_return_time,
            'new_estimated_return_time' => $this->new_estimated_return_time,
            'old_destination' => $this->old_destination,
            'new_destination' => $this->new_destination,
            'old_purpose' => $this->old_purpose,
            'new_purpose' => $this->new_purpose,
            'old_number_of_passengers' => $this->old_number_of_passengers,
            'new_number_of_passengers' => $this->new_number_of_passengers,
            'old_trip_end_date' => $this->old_trip_end_date?->toDateString(),
            'new_trip_end_date' => $this->new_trip_end_date?->toDateString(),
            'old_trip_type' => $this->old_trip_type,
            'new_trip_type' => $this->new_trip_type,
            'old_authorized_passengers' => $this->old_authorized_passengers,
            'new_authorized_passengers' => $this->new_authorized_passengers,
            'reason' => $this->reason,
            'admin_reason' => $this->admin_reason,
            'requested_by' => $this->requester?->full_name,
            'handled_by' => $this->handler?->full_name,
            'handled_at' => $this->handled_at,
            'created_at' => $this->created_at,
            'allocation_id' => $trip?->allocation?->id,
            'trip' => [
                'id' => $trip?->id,
                'destination' => $trip?->destination,
                'trip_date' => $trip?->trip_date ? \Carbon\Carbon::parse($trip->trip_date)->toDateString() : null,
                'trip_end_date' => $trip?->vehicleRequest?->trip_end_date?->toDateString()
                    ?? ($trip?->trip_date ? \Carbon\Carbon::parse($trip->trip_date)->toDateString() : null),
                'departure_time' => $trip?->departure_time,
                'estimated_return_time' => $trip?->estimated_return_time,
                'vehicle' => [
                    'id' => $trip?->vehicle?->id,
                    'plate_number' => $trip?->vehicle?->plate_number,
                    'vehicle_model' => $trip?->vehicle?->vehicle_model,
                    'capacity' => $trip?->vehicle?->capacity,
                ],
                'driver' => [
                    'id' => $trip?->driver?->id,
                    'name' => $trip?->driver?->full_name,
                    'contact_number' => $trip?->driver?->contact_number,
                ],
            ],
        ];
    }
}