<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Three rules from the capstone (Chapter III):
 * 1. Driver availability  2. Vehicle readiness  3. Assignment conflicts
 *
 * When an ACTIVE trip is reassigned, the vehicle or driver that is not being
 * replaced is not checked for "readiness" again. They are already marked
 * in use because of their own trip.
 */
class AllocationService
{
    /** Trip statuses that block double-booking. */
    public const ACTIVE = ['scheduled', 'in_progress'];

    /** Shape of an approved request in the allocation dropdown. */
    public static function requestOption(VehicleRequest $r): array
    {
        $tripDate = $r->trip_date->toDateString();

        return [
            'id' => $r->id,
            'label' => "#{$r->id} · {$r->destination} · {$tripDate} " . substr((string) $r->departure_time, 0, 5),
            'destination' => $r->destination,
            'purpose' => $r->purpose,
            'requester' => $r->requester?->full_name,
            'trip_date' => $tripDate,
            'trip_end_date' => $r->trip_end_date?->toDateString(),
            'trip_type' => $r->trip_type,
            'departure_time' => $r->departure_time,
            'estimated_return_time' => $r->estimated_return_time,
            'passengers' => $r->passengers,
            'number_of_passengers' => $r->number_of_passengers,
        ];
    }

    /** Shape of a vehicle in the dropdown (and in the quick-add response). */
    public static function vehicleOption(Vehicle $v): array
    {
        return [
            'id' => $v->id,
            'label' => "{$v->plate_number} — {$v->vehicle_model}" . ($v->capacity ? " ({$v->capacity} seats)" : ''),
            'vehicle_model' => $v->vehicle_model,
            'plate_number' => $v->plate_number,
            'capacity' => $v->capacity,
        ];
    }

    /** Shape of a driver in the dropdown: name and contact number. */
    public static function driverOption(Driver $d): array
    {
        $name = $d->full_name ?: "Driver #{$d->id}";
        $expiry = $d->license_expiry_date;

        return [
            'id' => $d->id,
            'label' => $name
                . ($d->contact_number ? " · {$d->contact_number}" : '')
                . ($expiry ? ' (Lic. exp. ' . $expiry->format('M Y') . ')' : ''),
            'name' => $name,
            'contact_number' => $d->contact_number,
            'license_expiry_date' => $expiry?->toDateString(),
        ];
    }

    /**
     * Move the date of a SCHEDULED trip (and of its vehicle request).
     * First checks that the current vehicle and driver are free on the new date.
     *
     * @return string the old date (YYYY-MM-DD)
     */
    public function moveTrip(Trip $trip, string $newDate): string
    {
        if ($trip->trip_status !== 'scheduled') {
            throw ValidationException::withMessages([
                'new_date' => "Only scheduled trips can have their date changed (this trip is {$trip->trip_status}).",
            ]);
        }

        $old = Carbon::parse($trip->trip_date)->toDateString();

        if ($newDate === $old) {
            throw ValidationException::withMessages(['new_date' => 'The new date is the same as the current date.']);
        }

        $driver = Driver::findOrFail($trip->driver_id);
        $this->assertLicenseValidOn($driver, $newDate);

        $this->assertFree('vehicle_id', $trip->vehicle_id, $newDate, $trip->departure_time, $trip->estimated_return_time, $trip->id);
        $this->assertFree('driver_id', $trip->driver_id, $newDate, $trip->departure_time, $trip->estimated_return_time, $trip->id);

        return $this->shiftTripDate($trip, $newDate);
    }

    /**
     * Writes the new date on the trip and on its vehicle request. No checks here.
     * The caller must run the conflict checks first.
     *
     * The end date moves by the same number of days, so a multi-day trip
     * never ends before it starts.
     *
     * @return string the old date (YYYY-MM-DD)
     */
    public function shiftTripDate(Trip $trip, string $newDate): string
    {
        $old = Carbon::parse($trip->trip_date)->toDateString();
        $vehicleRequest = VehicleRequest::findOrFail($trip->vehicle_request_id);
        $shift = (int) Carbon::parse($old)->diffInDays(Carbon::parse($newDate), false);

        DB::transaction(function () use ($trip, $newDate, $vehicleRequest, $shift) {
            Trip::where('id', $trip->id)->update(['trip_date' => $newDate]);

            $vehicleRequest->update([
                'trip_date' => $newDate,
                'trip_end_date' => $vehicleRequest->trip_end_date->copy()->addDays($shift)->toDateString(),
            ]);
        });

        return $old;
    }

    /**
     * @param  Trip|null  $currentTrip  pass this when reassigning an existing trip
     * @param  string|null  $newDate  pass this (YYYY-MM-DD) when the trip is also moved to another date.
     *                              The checks then run against the new date.
     */
    public function assertAssignable(
        VehicleRequest $vehicleRequest,
        Vehicle $vehicle,
        Driver $driver,
        ?Trip $currentTrip = null,
        ?string $newDate = null
    ): void {
        $date = $newDate ?? Carbon::parse($vehicleRequest->trip_date)->toDateString(); // 'YYYY-MM-DD'
        $start = $vehicleRequest->departure_time;
        $end = $vehicleRequest->estimated_return_time;
        $ignoreTripId = $currentTrip?->id;

        $this->assertRequestIsApproved($vehicleRequest);
        $this->assertNotAlreadyAllocated($vehicleRequest, $ignoreTripId);

        // Conflicts first (so the conflict modal shows up first), then readiness.
        $this->assertFree('vehicle_id', $vehicle->id, $date, $start, $end, $ignoreTripId);
        $this->assertFree('driver_id', $driver->id, $date, $start, $end, $ignoreTripId);

        if (! $currentTrip || (int) $vehicle->id !== (int) $currentTrip->vehicle_id) {
            $this->assertVehicleReady($vehicle);
        }

        if (! $currentTrip || (int) $driver->id !== (int) $currentTrip->driver_id) {
            $this->assertDriverReady($driver, $date);
        }

        // A driver who is kept on the trip still needs a valid license on the new date.
        if ($newDate) {
            $this->assertLicenseValidOn($driver, $date);
        }
    }

    /**
     * Active trips on the same date that overlap the given time.
     * If there is no return time, a 2-hour block is used so trips do not collide.
     */
    public function busyTrips(string $date, $start, ?string $end, ?int $ignoreTripId = null): Builder
    {
        $end = $end ?? date('H:i:s', strtotime($start) + 7200);

        return Trip::query()
            ->whereIn('trip_status', self::ACTIVE)
            ->whereDate('trip_date', $date)
            ->where('departure_time', '<', $end)
            ->where(function (Builder $q) use ($start) {
                $q->whereNull('estimated_return_time')
                    ->orWhere('estimated_return_time', '>', $start);
            })
            ->when($ignoreTripId, fn (Builder $q) => $q->where('id', '!=', $ignoreTripId));
    }

    protected function assertRequestIsApproved(VehicleRequest $vehicleRequest): void
    {
        if ($vehicleRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'vehicle_request_id' => 'Only approved vehicle requests can be allocated a vehicle and driver.',
            ]);
        }
    }

    protected function assertNotAlreadyAllocated(VehicleRequest $vehicleRequest, ?int $ignoreTripId): void
    {
        $existing = Trip::where('vehicle_request_id', $vehicleRequest->id)->first();

        if ($existing && $existing->id !== $ignoreTripId) {
            throw ValidationException::withMessages([
                'vehicle_request_id' => 'This request already has a trip allocated (trip #' . $existing->id . ').',
            ]);
        }
    }

    protected function assertVehicleReady(Vehicle $vehicle): void
    {
        // 'in_use' is allowed: the date and time block it (assertFree), not the status.
        if (! $vehicle->is_active || ! in_array($vehicle->status, ['available', 'in_use'], true)) {
            throw ValidationException::withMessages([
                'vehicle_id' => "This vehicle is currently '{$vehicle->status}' and is not ready for dispatch.",
            ]);
        }
    }

    /**
     * A driver is a record only (no login), so only availability and license are checked.
     * The license is checked against the TRIP DATE, not today.
     */
    protected function assertDriverReady(Driver $driver, string $tripDate): void
    {
        // is_available = false only because of another trip is not a problem (assertFree handles it).
        // It blocks only when the driver was marked unavailable by hand and has no active trip.
        $hasActiveTrip = Trip::where('driver_id', $driver->id)->whereIn('trip_status', self::ACTIVE)->exists();

        if (! $driver->is_available && ! $hasActiveTrip) {
            throw ValidationException::withMessages([
                'driver_id' => 'This driver is currently marked unavailable.',
            ]);
        }

        $this->assertLicenseValidOn($driver, $tripDate);
    }

    protected function assertLicenseValidOn(Driver $driver, string $date): void
    {
        $expiry = $driver->license_expiry_date?->toDateString();

        if ($expiry && $expiry < $date) {
            throw ValidationException::withMessages([
                'driver_id' => "This driver's license expires before the trip date.",
            ]);
        }
    }

    /**
     * One check for both driver and vehicle.
     *
     * @param  string  $column  'driver_id' or 'vehicle_id' (the column in trips and the error field name)
     */
    protected function assertFree(string $column, int $id, string $date, $start, ?string $end, ?int $ignoreTripId): void
    {
        $conflict = $this->busyTrips($date, $start, $end, $ignoreTripId)->where($column, $id)->first();

        if (! $conflict) {
            return;
        }

        $conflict->loadMissing(['vehicle', 'driver']);

        $isVehicle = $column === 'vehicle_id';
        $vehicleLabel = $conflict->vehicle
            ? trim("{$conflict->vehicle->vehicle_model} ({$conflict->vehicle->plate_number})")
            : null;
        $driverLabel = $conflict->driver?->full_name;

        // The subject is the one being assigned, which is also the one already on the conflicting trip.
        $subjectName = $isVehicle ? ($vehicleLabel ?? 'Vehicle') : ($driverLabel ?: "Driver #{$id}");

        $when = Carbon::parse($conflict->trip_date)->format('F j, Y');
        $message = "{$subjectName} is already scheduled on {$when} at {$conflict->departure_time}"
            . ($conflict->estimated_return_time ? " – {$conflict->estimated_return_time}" : '') . '.';

        // 422 with "conflict" details for the conflict modal of the frontend.
        throw new HttpResponseException(response()->json([
            'message' => $message,
            'errors' => [$column => [$message]],
            'conflict' => [
                'type' => $isVehicle ? 'vehicle' : 'driver',
                'subject' => $subjectName,
                'trip_id' => $conflict->id,
                'date' => Carbon::parse($conflict->trip_date)->toDateString(),
                'start' => $conflict->departure_time,
                'end' => $conflict->estimated_return_time,
                'destination' => $conflict->destination,
                'driver' => $driverLabel,
                'vehicle' => $vehicleLabel,
            ],
        ], 422));
    }
}