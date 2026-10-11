<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ito LANG ang model na pag-aari ng allocation module.
 * Trip, Driver, VehicleRequest, Vehicle at User ay galing sa ibang modules
 * (trip-scheduling, vehicle-request, atbp.) — hindi na sila kasama dito.
 *
 * Audit record ito (sino ang nag-assign ng ano, kailan) na nakaturo sa
 * existing na `trips` row. Ang live status ay nasa Trip::trip_status.
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

    // driver_id -> drivers.id (hindi users.id)
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function allocatedBy()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    /**
     * Mga relationship na kailangan ng module sa models ng ibang modules.
     * (Driver ay record lang — walang login/account, kaya walang Driver::user dito.)
     * Ginagamit ang resolveRelationUsing() para hindi na kailangang kopyahin
     * o i-edit ang Trip.php / Driver.php / VehicleRequest.php ng teammates.
     * Kung may sarili na silang method na ganito ang pangalan, iyon ang
     * gagamitin (hindi ito mao-overwrite).
     */
    public static function registerSharedRelations(): void
    {
        Trip::resolveRelationUsing('vehicleRequest', fn (Trip $trip) => $trip->belongsTo(VehicleRequest::class));
        VehicleRequest::resolveRelationUsing('requester', fn (VehicleRequest $r) => $r->belongsTo(User::class, 'requester_id'));
        VehicleRequest::resolveRelationUsing('trip', fn (VehicleRequest $r) => $r->hasOne(Trip::class));
    }
}
