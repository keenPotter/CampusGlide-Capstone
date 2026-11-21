<?php

namespace App\Http\Resources;

use App\Models\VehicleRequest;
use App\Models\RescheduleRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pendingModification = null;
        $trip = $this->trip;
        if ($trip) {
            $pendingModification = RescheduleRequest::where('trip_id', $trip->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();
        }
        return [
            'id' => $this->id,
            'status' => $this->status,
            'booking_source' => $this->booking_source ?? VehicleRequest::SOURCE_ONLINE,
            'requester_name' => $this->requester_name ?: $this->requester?->full_name,
            'requester_department' => $this->requester_department,
            'requester_contact' => $this->requester_contact,
            'destination' => $this->destination,
            'purpose' => $this->purpose,
            'trip_date' => $this->trip_date?->format('Y-m-d'),
            'trip_end_date' => $this->trip_end_date?->format('Y-m-d'),
            'travel_days' => $this->travel_days,
            'trip_type' => $this->trip_type,
            'departure_time' => $this->departure_time,
            'return_time' => $this->estimated_return_time,
            'passengers' => $this->passengers,
            'number_of_passengers' => $this->number_of_passengers,
            'disapproval_reason' => $this->disapproval_reason,
            // Reason the requester gave when editing the request after it was approved.
            'edit_reason' => $this->edit_reason,
            'approved_by' => $this->approved_by,
            'approved_date' => $this->approved_date?->toIso8601String(),
            'modification_pending' => $pendingModification !== null,
            'pending_modification_request' => $pendingModification ? [
                'id' => $pendingModification->id,
                'new_date' => $pendingModification->new_date?->toDateString(),
                'new_departure_time' => $pendingModification->new_departure_time,
                'new_estimated_return_time' => $pendingModification->new_estimated_return_time,
                'new_destination' => $pendingModification->new_destination,
                'new_purpose' => $pendingModification->new_purpose,
                'new_number_of_passengers' => $pendingModification->new_number_of_passengers,
                'reason' => $pendingModification->reason,
            ] : null,
            'requested_by' => $this->requester ? [
                'id' => $this->requester->id,
                'first_name' => $this->requester->first_name,
                'last_name' => $this->requester->last_name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}