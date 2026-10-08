<?php

namespace App\Http\Resources;

use App\Services\AllocationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $trip = $this->trip;
        $requester = $trip?->vehicleRequest?->requester;

        return [
            'id' => $this->id,
            'status' => $trip?->trip_status,
            'trip' => [
                'id' => $trip?->id,
                'trip_date' => $trip?->trip_date ? Carbon::parse($trip->trip_date)->toDateString() : null,
                'departure_time' => $trip?->departure_time,
                'estimated_return_time' => $trip?->estimated_return_time,
                'destination' => $trip?->destination,
                'purpose' => $trip?->purpose,
            ],
            'vehicle_request' => [
                'id' => $trip?->vehicleRequest?->id,
                'requester' => self::fullName($requester),
            ],
            'vehicle' => [
                'id' => $this->vehicle?->id,
                'plate_number' => $this->vehicle?->plate_number,
                'vehicle_model' => $this->vehicle?->vehicle_model,
            ],
            // Driver ay record lang: pangalan + contact number + license.
            'driver' => [
                'id' => $this->driver?->id,
                'name' => AllocationService::driverName($this->driver),
                'contact_number' => $this->driver?->getAttributes()['contact_number'] ?? null,
                'license_number' => $this->driver?->license_number,
            ],
            'allocated_by' => self::fullName($this->allocatedBy),
            'allocation_date' => $this->allocation_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private static function fullName($user): ?string
    {
        return $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : null;
    }
}
