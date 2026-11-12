<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'trip_id',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }
}
