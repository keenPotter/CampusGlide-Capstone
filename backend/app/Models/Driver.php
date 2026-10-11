<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'license_number',
        'license_expiry_date',
        'contact_number',
        'address',
        'assigned_vehicle_id',
        'is_available',
    ];
}