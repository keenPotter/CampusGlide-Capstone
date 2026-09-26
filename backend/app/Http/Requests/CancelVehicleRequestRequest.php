<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelVehicleRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vehicleRequest = $this->route('vehicleRequest');

        return $vehicleRequest
            && (int) $vehicleRequest->requester_id === (int) $this->user()->id
            && in_array($vehicleRequest->status, ['pending', 'approved'], true);
    }

    public function rules(): array
    {
        return [
            'cancellation_remarks' => ['required', 'string', 'max:500'],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'cancellation_remarks' => trim((string) $this->cancellation_remarks),
        ]);
    }
}