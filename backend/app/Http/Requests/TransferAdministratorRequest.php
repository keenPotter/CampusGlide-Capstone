<?php

namespace App\Http\Requests;

class TransferAdministratorRequest extends RegisterRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['role']);

        return $rules;
    }
}
