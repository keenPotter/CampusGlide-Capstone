<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuardLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('guard') ?? false;
    }

    public function rules(): array
    {
        return [
            'vehicle_request_id' => [
                'required',
                'integer',
                'exists:vehicle_requests,id',
                'unique:guard_logs,vehicle_request_id',
            ],
            'vehicle_used' => ['nullable', 'string', 'max:255'],
            'actual_departure_date' => ['required', 'date'],
            'actual_departure_time' => ['required', 'date_format:H:i'],
            'vehicle_condition_departure' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_request_id.unique' => 'A departure has already been logged for this request.',
        ];
    }
}