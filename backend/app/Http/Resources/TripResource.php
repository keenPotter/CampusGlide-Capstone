<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\RescheduleRequest;

/**
 * The field names match what the frontend trip schedule pages read
 * (vehicle_request, vehicle, driver).
 */
class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $vehicleRequest = $this->vehicleRequest;
        $requester = $vehicleRequest?->requester;
        $pendingModification = RescheduleRequest::where('trip_id', $this->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        return [
            'id' => $this->id,
            'allocation_id' => $this->allocation?->id,
            'modification_pending' => $pendingModification !== null,
            'pending_modification_request' => $pendingModification ? [
                'id' => $pendingModification->id,
                'old_date' => $pendingModification->old_date?->toDateString(),
                'new_date' => $pendingModification->new_date?->toDateString(),
                'new_departure_time' => $pendingModification->new_departure_time,
                'new_estimated_return_time' => $pendingModification->new_estimated_return_time,
                'new_destination' => $pendingModification->new_destination,
                'new_purpose' => $pendingModification->new_purpose,
                'new_number_of_passengers' => $pendingModification->new_number_of_passengers,
                'reason' => $pendingModification->reason,
            ] : null,
            'vehicle_request_id' => $this->vehicle_request_id,
            'vehicle_id' => $this->vehicle_id,
            'driver_id' => $this->driver_id,
            'trip_date' => $this->trip_date?->toDateString(),
            'departure_time' => $this->departure_time,
            'estimated_return_time' => $this->estimated_return_time,
            'destination' => $this->destination,
            'purpose' => $this->purpose,
            'trip_status' => $this->trip_status,
            'actual_departure_time' => $this->actual_departure_time,
            'actual_return_time' => $this->actual_return_time,
            'actual_mileage' => $this->actual_mileage,
            'notes' => $this->notes,
            'vehicle_request' => $vehicleRequest ? [
                'id' => $vehicleRequest->id,
                'status' => $vehicleRequest->status,
                'trip_type' => $vehicleRequest->trip_type,
                'trip_date' => $vehicleRequest->trip_date?->toDateString(),
                'trip_end_date' => $vehicleRequest->trip_end_date?->toDateString(),
                'number_of_passengers' => $vehicleRequest->number_of_passengers,
                'booking_source' => $vehicleRequest->booking_source ?? 'online',
                'requester_name' => $vehicleRequest->requester_name ?: $requester?->full_name,
                'requester_department' => $vehicleRequest->requester_department,
                'requester_contact' => $vehicleRequest->requester_contact,
                'requester' => $requester ? [
                    'id' => $requester->id,
                    'first_name' => $requester->first_name,
                    'last_name' => $requester->last_name,
                ] : null,
            ] : null,
            'vehicle' => $this->vehicle ? [
                'id' => $this->vehicle->id,
                'plate_number' => $this->vehicle->plate_number,
                'vehicle_model' => $this->vehicle->vehicle_model,
            ] : null,
            'driver' => $this->driver ? [
                'id' => $this->driver->id,
                'first_name' => $this->driver->first_name,
                'last_name' => $this->driver->last_name,
                'name' => $this->driver->full_name,
                'contact_number' => $this->driver->contact_number,
            ] : null,
        ];
    }
}