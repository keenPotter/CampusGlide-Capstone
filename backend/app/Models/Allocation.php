<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Audit record of who assigned which vehicle and driver to a trip, and when.
 * It points to an existing `trips` row. The live status is kept in Trip::trip_status.
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
        'ruv_number',
    ];

    protected function casts(): array
    {
        return [
            'allocation_date' => 'datetime',
        ];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    // driver_id points to drivers.id (not users.id)
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function allocatedBy()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    /**
     * RUV No. of the Request for Use of Vehicle. Format: YYYY-NNNN
     * (2026-0001, 2026-0002, ...). The count starts again every year.
     * It is made only once per allocation, so the number stays the same
     * no matter how many times the form is printed.
     */
    public function ensureRuvNumber(): string
    {
        if ($this->ruv_number) {
            return $this->ruv_number;
        }

        return DB::transaction(function () {
            $current = static::whereKey($this->getKey())->lockForUpdate()->first();

            if ($current && $current->ruv_number) {
                $this->ruv_number = $current->ruv_number;

                return $this->ruv_number;
            }

            $prefix = now()->year . '-';
            $last = static::where('ruv_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('ruv_number')
                ->value('ruv_number');

            $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            $this->ruv_number = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $this->save();

            return $this->ruv_number;
        });
    }
}