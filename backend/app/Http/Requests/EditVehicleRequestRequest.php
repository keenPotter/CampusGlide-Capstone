<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditVehicleRequestRequest extends FormRequest
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
        // A reason is required only when the request is already approved.
        $isApproved = $this->route('vehicleRequest')?->status === 'approved';

        return [
            'trip_date' => [$isApproved ? 'required' : 'required', 'date'],
            'trip_end_date' => [$isApproved ? 'nullable' : 'required', 'date', 'after_or_equal:trip_date'],
            'trip_type' => [$isApproved ? 'nullable' : 'required', 'in:inclusive,exclusive'],
            'departure_time' => ['required', 'date_format:H:i'],
            'destination' => [$isApproved ? 'nullable' : 'required', 'string', 'max:255'],
            'purpose' => [$isApproved ? 'nullable' : 'required', 'string', 'max:255'],
            'estimated_return_time' => [$isApproved ? 'nullable' : 'required', 'date_format:H:i', 'after:departure_time'],
            'passengers' => [$isApproved ? 'nullable' : 'required', 'string', 'max:255'],
            'number_of_passengers' => [$isApproved ? 'nullable' : 'required', 'integer', 'min:1'],
            'edit_reason' => [$isApproved ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'edit_reason.required' => 'Please give a reason for this Date Change Request.',
        ];
    }
}