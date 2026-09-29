<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleRequest extends Model
{
    protected $table = 'vehicle_requests';

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

    public function trip()
    {
        return $this->hasOne(Trip::class, 'vehicle_request_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
