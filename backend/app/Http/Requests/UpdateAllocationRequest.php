<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'administrator';
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['sometimes', 'required', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['sometimes', 'required', 'integer', 'exists:drivers,id'],
            // Matches trips.trip_status enum in campusglide_db.sql
            // (the allocation record itself has no status column).
            'status' => ['sometimes', 'required', 'in:scheduled,in_progress,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
