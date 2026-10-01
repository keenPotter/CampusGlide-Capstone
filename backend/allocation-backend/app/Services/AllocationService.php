<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Illuminate\Validation\ValidationException;

/**
 * Tatlong rule mula sa capstone (Chapter III):
 * 1. Driver availability  2. Vehicle readiness  3. Assignment conflicts
 *
 * FIXED: kapag nire-reassign ang isang ACTIVE trip, ang vehicle/driver na
 * hindi pinalitan ay hindi na chine-check sa "readiness" (in_use / unavailable
 * na sila dahil sa sarili nilang trip). Dati nito, laging 422 ang Reassign.
 */
class AllocationService
{
    /**
     * @param  Trip|null  $currentTrip  ipasa kapag nire-reassign (existing trip)
     */
    public function assertAssignable(
        VehicleRequest $vehicleRequest,
        Vehicle $vehicle,
        Driver $driver,
        ?Trip $currentTrip = null
    ): void {
        $date = $vehicleRequest->trip_date->toDateString(); // 'YYYY-MM-DD'
        $start = $vehicleRequest->departure_time;
        $end = $vehicleRequest->estimated_return_time;
        $ignoreTripId = $currentTrip?->id;

        $this->assertRequestIsApproved($vehicleRequest);
        $this->assertNotAlreadyAllocated($vehicleRequest, $ignoreTripId);

        if (! $currentTrip || $vehicle->id !== $currentTrip->vehicle_id) {
            $this->assertVehicleReady($vehicle);
        }

        if (! $currentTrip || $driver->id !== $currentTrip->driver_id) {
            $this->assertDriverReady($driver, $date);
        }

        $this->assertDriverAvailable($driver, $date, $start, $end, $ignoreTripId);
        $this->assertVehicleAvailable($vehicle, $date, $start, $end, $ignoreTripId);
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
        $existing = $vehicleRequest->trip;

        if ($existing && $existing->id !== $ignoreTripId) {
            throw ValidationException::withMessages([
                'vehicle_request_id' => 'This request already has a trip allocated (trip #' . $existing->id . ').',
            ]);
        }
    }

    protected function assertVehicleReady(Vehicle $vehicle): void
    {
        if (! $vehicle->is_active || $vehicle->status !== 'available') {
            throw ValidationException::withMessages([
                'vehicle_id' => "This vehicle is currently '{$vehicle->status}' and is not ready for dispatch.",
            ]);
        }
    }

    /** License is checked against the TRIP DATE (not today). */
    protected function assertDriverReady(Driver $driver, string $tripDate): void
    {
        if (! $driver->is_available) {
            throw ValidationException::withMessages([
                'driver_id' => 'This driver is currently marked unavailable.',
            ]);
        }

        if (! $driver->user || ! $driver->user->is_active) {
            throw ValidationException::withMessages([
                'driver_id' => "This driver's account is inactive.",
            ]);
        }

        if ($driver->license_expiry_date && $driver->license_expiry_date->toDateString() < $tripDate) {
            throw ValidationException::withMessages([
                'driver_id' => "This driver's license expires before the trip date.",
            ]);
        }
    }

    protected function assertDriverAvailable(Driver $driver, string $date, $start, ?string $end, ?int $ignoreTripId): void
    {
        $conflict = Trip::query()
            ->active()
            ->forDriver($driver->id)
            ->overlapping($date, $start, $end)
            ->except($ignoreTripId)
            ->first();

        if ($conflict) {
            throw ValidationException::withMessages([
                'driver_id' => "Driver is already assigned to trip #{$conflict->id} "
                    . "on {$conflict->trip_date->format('M d, Y')} at {$conflict->departure_time}.",
            ]);
        }
    }

    protected function assertVehicleAvailable(Vehicle $vehicle, string $date, $start, ?string $end, ?int $ignoreTripId): void
    {
        $conflict = Trip::query()
            ->active()
            ->forVehicle($vehicle->id)
            ->overlapping($date, $start, $end)
            ->except($ignoreTripId)
            ->first();

        if ($conflict) {
            throw ValidationException::withMessages([
                'vehicle_id' => "Vehicle is already assigned to trip #{$conflict->id} "
                    . "on {$conflict->trip_date->format('M d, Y')} at {$conflict->departure_time}.",
            ]);
        }
    }
}
