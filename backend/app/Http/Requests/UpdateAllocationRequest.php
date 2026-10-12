<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only administrators can reassign a vehicle or driver.
        return $this->user()?->hasRole('administrator') ?? false;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['sometimes', 'required', 'integer', 'exists:vehicles,id'],
            // drivers.id, not users.id
            'driver_id' => ['sometimes', 'required', 'integer', 'exists:drivers,id'],
            // Matches the trips.trip_status enum (the allocations table has no status column).
            'status' => ['sometimes', 'required', 'in:scheduled,in_progress,completed'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.exists' => 'Vehicle not found.',
            'driver_id.exists' => 'Driver not found.',
        ];
    }
}