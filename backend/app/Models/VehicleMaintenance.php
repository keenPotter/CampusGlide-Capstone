<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleMaintenance extends Model
{
    use HasFactory;

    protected $table = 'vehicle_maintenance';

    // Column names match the database exactly — no renaming.
    protected $fillable = [
        'vehicle_id',
        'maintenance_type',
        'description',
        'maintenance_date',
        'completion_date',
        'next_due_date',
        'cost',
        'performed_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'maintenance_date' => 'date:Y-m-d',
        'completion_date'  => 'date:Y-m-d',
        'next_due_date'    => 'date:Y-m-d',
        'cost'             => 'decimal:2',
    ];

    public const TYPES = [
        'oil_change',
        'repair',
        'refueling',
        'inspection',
        'tire_service',
        'other',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
