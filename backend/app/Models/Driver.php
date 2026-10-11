<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * NEW — did not exist in the original upload. In campusglide_db,
 * "driver" is its own table (license info, availability), linked to
 * users via user_id — it is NOT the same row as a `users` record with
 * role='driver'. If this file already exists elsewhere in your app
 * (e.g. from the driver-management module), just add the two
 * relationship methods below to it instead of replacing the file.
 */
class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'license_number',
        'license_expiry_date',
        'contact_number',
        'address',
        'assigned_vehicle_id',
        'is_available',
    ];

    protected $casts = [
        'license_expiry_date' => 'date',
        'is_available' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedVehicle()
    {
        return $this->belongsTo(Vehicle::class, 'assigned_vehicle_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }
}
