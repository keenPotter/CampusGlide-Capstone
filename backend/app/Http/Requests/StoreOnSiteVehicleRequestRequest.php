<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOnSiteVehicleRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('administrator') ?? false;
    }

    public function rules(): array
    {
        return [
            'requester_name' => ['required', 'string', 'max:255'],
            'requester_department' => ['required', 'string', 'max:255'],
            'requester_contact' => ['required', 'string', 'max:50'],
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'trip_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'trip_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:trip_date'],
            'trip_type' => ['required', 'in:inclusive,exclusive'],
            'departure_time' => ['required', 'date_format:H:i'],
            'estimated_return_time' => ['required', 'date_format:H:i', 'after:departure_time'],
            'passengers' => ['required', 'string', 'max:255'],
            'number_of_passengers' => ['required', 'integer', 'min:1'],
            'vehicle_id' => ['nullable', 'required_with:driver_id', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'required_with:vehicle_id', 'integer', 'exists:drivers,id'],
        ];
    }
}
