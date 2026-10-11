<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Request para palitan ang petsa ng trip (galing sa Faculty),
 * o record ng direct na pagpapalit ng Admin (status 'approved' agad).
 */
class RescheduleRequest extends Model
{
    protected $fillable = [
        'trip_id', 'requested_by', 'old_date', 'new_date', 'reason',
        'status', 'handled_by', 'admin_reason', 'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
