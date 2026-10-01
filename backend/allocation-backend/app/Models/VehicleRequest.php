<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * NEW — the original upload assumed a `TripRequest` model/table.
 * campusglide_db calls this table `vehicle_requests`, and its columns
 * are trip_date + departure_time + estimated_return_time (separate
 * DATE/TIME fields) rather than a single departure_datetime /
 * return_datetime pair. If Keen's Vehicle Request module already has
 * this file, just make sure it has the two relationships below —
 * don't overwrite whatever else is already in it.
 */
class VehicleRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_id',
        'request_date',
        'trip_date',
        'departure_time',
        'destination',
        'purpose',
        'estimated_return_time',
        'number_of_passengers',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_date',
    ];

    protected $casts = [
        'trip_date' => 'date',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function trip()
    {
        return $this->hasOne(Trip::class);
    }
}
