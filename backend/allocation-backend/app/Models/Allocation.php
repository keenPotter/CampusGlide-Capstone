<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * UPDATED to match campusglide_db.sql. In the real schema, Allocation
 * is an audit record (who assigned what, when) that points at an
 * existing `trips` row — it does not carry its own status and it
 * does not point at the vehicle_request directly. The live trip
 * status (scheduled / in_progress / completed / cancelled) lives on
 * Trip::trip_status, not here.
 */
class Allocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'vehicle_id',
        'driver_id',
        'allocated_by',
        'allocation_date',
        'notes',
    ];

    protected $casts = [
        'allocation_date' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    // driver_id -> drivers.id (not users.id)
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function allocatedBy()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
