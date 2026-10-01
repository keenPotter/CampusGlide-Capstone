<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'user_id',
        'license_number',
        'license_expiry_date',
        'contact_number',
        'address',
        'assigned_vehicle_id',
        'is_available',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}