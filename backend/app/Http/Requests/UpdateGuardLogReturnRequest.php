<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuardLogReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('guard') ?? false;
    }

    public function rules(): array
    {
        return [
            'actual_return_date' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    $departureDate = $this->route('guardLog')?->actual_departure_date;

                    if ($departureDate && $value < $departureDate->format('Y-m-d')) {
                        $fail('The return date cannot be before the departure date.');
                    }
                },
            ],
            'actual_return_time' => ['required', 'date_format:H:i'],
            'vehicle_condition_return' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}