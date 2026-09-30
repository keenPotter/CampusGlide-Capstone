<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuardLog extends Model
{
    protected $fillable = [
        'vehicle_request_id',
        'guard_id',
        'vehicle_used',
        'actual_departure_date',
        'actual_departure_time',
        'actual_return_date',
        'actual_return_time',
        'vehicle_condition_departure',
        'vehicle_condition_return',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'actual_departure_date' => 'date',
            'actual_return_date' => 'date',
        ];
    }

    public function vehicleRequest()
    {
        return $this->belongsTo(VehicleRequest::class);
    }

    public function guardUser()
    {
        return $this->belongsTo(User::class, 'guard_id');
    }
}