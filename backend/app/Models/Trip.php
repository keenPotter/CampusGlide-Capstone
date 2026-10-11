<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * NEW — did not exist in the original upload. campusglide_db.sql has
 * a `trips` table that sits between vehicle_requests and allocations:
 * a trip is created the moment a vehicle+driver are assigned, and it
 * carries the live `trip_status`. Add this file (or, if a teammate
 * already created Trip.php for the scheduling module, just merge in
 * the scopes below instead of overwriting theirs).
 */
class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_request_id',
        'vehicle_id',
        'driver_id',
        'trip_date',
        'departure_time',
        'estimated_return_time',
        'destination',
        'purpose',
        'trip_status',
        'actual_departure_time',
        'actual_return_time',
        'actual_mileage',
        'notes',
    ];

    protected $casts = [
        'trip_date' => 'date',
    ];

    // ---- Relationships ----

    public function vehicleRequest()
    {
        return $this->belongsTo(VehicleRequest::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function allocation()
    {
        return $this->hasOne(Allocation::class);
    }

    // ---- Query scopes for conflict checking ----
    // (equivalent to the scopes that used to live on Allocation.php)

    /**
     * Trips that are still "live" and therefore block a driver or
     * vehicle from being double-booked.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('trip_status', ['scheduled', 'in_progress']);
    }

    /**
     * Trips on the same date whose time window overlaps the given
     * departure/return time. A null $end is treated as a 2-hour block
     * so back-to-back requests don't silently collide.
     */
    public function scopeOverlapping(Builder $query, string $tripDate, string $departureTime, ?string $returnTime): Builder
    {
        $end = $returnTime ?? date('H:i:s', strtotime($departureTime) + 7200);

        return $query->whereDate('trip_date', $tripDate)
            ->where('departure_time', '<', $end)
            ->where(function (Builder $q) use ($departureTime) {
                $q->whereNull('estimated_return_time')
                    ->orWhere('estimated_return_time', '>', $departureTime);
            });
    }

    public function scopeForDriver(Builder $query, int $driverId): Builder
    {
        return $query->where('driver_id', $driverId);
    }

    public function scopeForVehicle(Builder $query, int $vehicleId): Builder
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Exclude a specific trip id — used when checking conflicts during
     * a reassignment so the trip doesn't conflict with itself.
     */
    public function scopeExcept(Builder $query, ?int $tripId): Builder
    {
        return $tripId ? $query->where('id', '!=', $tripId) : $query;
    }
}
