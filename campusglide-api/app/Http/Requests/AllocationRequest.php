<?php

namespace App\Http\Requests;

use App\Services\AllocationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Isang request class para sa store (POST) at update (PUT/PATCH).
 */
class AllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Admin lang ang pwedeng mag-assign / mag-reassign.
        return AllocationService::isAdmin($this->user());
    }

    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'vehicle_request_id' => ['required', 'integer', 'exists:vehicle_requests,id'],
                'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
                'driver_id' => ['required', 'integer', 'exists:drivers,id'], // drivers.id, hindi users.id
                'notes' => ['nullable', 'string', 'max:500'],
            ];
        }

        return [
            'vehicle_id' => ['sometimes', 'required', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['sometimes', 'required', 'integer', 'exists:drivers,id'],
            // trips.trip_status enum (walang status column ang allocations table)
            'status' => ['sometimes', 'required', 'in:scheduled,in_progress,completed'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_request_id.exists' => 'Vehicle request not found.',
            'vehicle_id.exists' => 'Vehicle not found.',
            'driver_id.exists' => 'Driver not found.',
        ];
    }
}
