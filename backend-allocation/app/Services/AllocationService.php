<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tatlong rule mula sa capstone (Chapter III):
 * 1. Driver availability  2. Vehicle readiness  3. Assignment conflicts
 *
 * Kapag nire-reassign ang isang ACTIVE trip, ang vehicle/driver na hindi
 * pinalitan ay hindi na chine-check sa "readiness" (in_use / unavailable na
 * sila dahil sa sarili nilang trip).
 *
 * Ang conflict query ay nandito na (busyTrips) — hindi na kailangan ng mga
 * scope sa Trip model ng ibang module.
 */
class AllocationService
{
    /** Trip status na humaharang sa double-booking. */
    public const ACTIVE = ['scheduled', 'in_progress'];

    /**
     * Dalawa lang ang klase ng user: ADMIN at END USER.
     * (Chief Motorpool at Boss ay parehong Admin.)
     * Ang role na nasa listahang ito ay admin; LAHAT ng iba pang naka-login ay end user.
     * Kung ibang pangalan ang role ng admin sa DB, idagdag dito (at sa frontend api.js).
     */
    public const ADMIN_ROLES = ['administrator', 'admin'];

    public static function isAdmin($user): bool
    {
        return $user !== null && in_array($user->role, self::ADMIN_ROLES, true);
    }

    /**
     * Driver ay RECORD LANG (walang login): kinukuha ang pangalan sa mismong driver record
     * (name, o first_name + last_name). Fallback lang ang naka-link na user (lumang data).
     * Hindi umaasa sa relationship kaya ligtas kahit anong columns ang meron.
     */
    public static function driverName($driver): ?string
    {
        if (! $driver) {
            return null;
        }

        $attrs = $driver->getAttributes();
        $own = trim((string) ($attrs['name'] ?? (($attrs['first_name'] ?? '') . ' ' . ($attrs['last_name'] ?? ''))));

        if ($own !== '') {
            return $own;
        }

        $user = ! empty($attrs['user_id']) ? User::find($attrs['user_id']) : null;
        $legacy = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : '';

        return $legacy !== '' ? $legacy : null;
    }

    /** Hugis ng vehicle sa dropdown (at sa quick-add response). */
    public static function vehicleOption($v): array
    {
        $a = $v->getAttributes();
        $cap = $a['capacity'] ?? null;

        return [
            'id' => $v->id,
            'label' => "{$v->plate_number} — {$v->vehicle_model}" . ($cap ? " ({$cap} seats)" : ''),
            'vehicle_model' => $v->vehicle_model,
            'plate_number' => $v->plate_number,
            'capacity' => $cap,
        ];
    }

    /** Hugis ng driver sa dropdown: pangalan + contact number. */
    public static function driverOption($d): array
    {
        $a = $d->getAttributes();
        $name = self::driverName($d) ?? "Driver #{$d->id}";
        $contact = $a['contact_number'] ?? null;
        $expiry = ! empty($a['license_expiry_date']) ? Carbon::parse($a['license_expiry_date']) : null;

        return [
            'id' => $d->id,
            'label' => $name . ($contact ? " · {$contact}" : '') . ($expiry ? ' (Lic. exp. ' . $expiry->format('M Y') . ')' : ''),
            'name' => $name,
            'contact_number' => $contact,
            'license_expiry_date' => $expiry?->toDateString(),
        ];
    }

    /**
     * Ilipat ang petsa ng isang SCHEDULED na trip (pati ang vehicle request nito).
     * Sinusuri muna kung libre ang kasalukuyang vehicle at driver sa bagong petsa.
     *
     * @return string ang lumang petsa (YYYY-MM-DD)
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

        $this->assertFree('vehicle_id', 'Vehicle', $trip->vehicle_id, $newDate, $trip->departure_time, $trip->estimated_return_time, $trip->id);
        $this->assertFree('driver_id', 'Driver', $trip->driver_id, $newDate, $trip->departure_time, $trip->estimated_return_time, $trip->id);

        DB::transaction(function () use ($trip, $newDate) {
            Trip::where('id', $trip->id)->update(['trip_date' => $newDate]);
            VehicleRequest::where('id', $trip->vehicle_request_id)->update(['trip_date' => $newDate]);
        });

        return $old;
    }

    /**
     * @param  Trip|null  $currentTrip  ipasa kapag nire-reassign (existing trip)
     */
    public function assertAssignable(
        VehicleRequest $vehicleRequest,
        Vehicle $vehicle,
        Driver $driver,
        ?Trip $currentTrip = null
    ): void {
        $date = Carbon::parse($vehicleRequest->trip_date)->toDateString(); // 'YYYY-MM-DD'
        $start = $vehicleRequest->departure_time;
        $end = $vehicleRequest->estimated_return_time;
        $ignoreTripId = $currentTrip?->id;

        $this->assertRequestIsApproved($vehicleRequest);
        $this->assertNotAlreadyAllocated($vehicleRequest, $ignoreTripId);

        // Conflict muna (para ang modal ang unang lalabas), saka ang readiness.
        $this->assertFree('vehicle_id', 'Vehicle', $vehicle->id, $date, $start, $end, $ignoreTripId);
        $this->assertFree('driver_id', 'Driver', $driver->id, $date, $start, $end, $ignoreTripId);

        if (! $currentTrip || $vehicle->id !== $currentTrip->vehicle_id) {
            $this->assertVehicleReady($vehicle);
        }

        if (! $currentTrip || $driver->id !== $currentTrip->driver_id) {
            $this->assertDriverReady($driver, $date);
        }
    }

    /**
     * Mga active na trip sa parehong petsa na nag-o-overlap sa oras na ibinigay.
     * Kapag walang return time, 2-hour block ang ituturing para hindi mag-banggaan.
     * Ginagamit din ng controller para i-filter ang dropdown options.
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
        // 'in_use' ay okay: ang oras/petsa ang humaharang (assertFree), hindi ang status.
        if (! $vehicle->is_active || ! in_array($vehicle->status, ['available', 'in_use'], true)) {
            throw ValidationException::withMessages([
                'vehicle_id' => "This vehicle is currently '{$vehicle->status}' and is not ready for dispatch.",
            ]);
        }
    }

    /**
     * Driver ay record lang (walang login), kaya availability + license lang ang tinitingnan.
     * License is checked against the TRIP DATE (hindi ngayon).
     */
    protected function assertDriverReady(Driver $driver, string $tripDate): void
    {
        // is_available = false dahil lang sa ibang trip ay hindi harang (assertFree ang bahala).
        // Harang lang kapag manu-manong unavailable at walang active trip.
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
        if ($driver->license_expiry_date && Carbon::parse($driver->license_expiry_date)->toDateString() < $date) {
            throw ValidationException::withMessages([
                'driver_id' => "This driver's license expires before the trip date.",
            ]);
        }
    }

    /**
     * Isang check para sa driver at vehicle.
     *
     * @param  string  $column  'driver_id' o 'vehicle_id' (column sa trips at pangalan ng error field)
     */
    protected function assertFree(string $column, string $label, int $id, string $date, $start, ?string $end, ?int $ignoreTripId): void
    {
        $conflict = $this->busyTrips($date, $start, $end, $ignoreTripId)->where($column, $id)->first();

        if (! $conflict) {
            return;
        }

        $isVehicle = $column === 'vehicle_id';
        $subject = $isVehicle ? Vehicle::find($id) : Driver::find($id);
        $subjectName = $isVehicle
            ? trim(($subject?->vehicle_model ?? 'Vehicle') . ' (' . ($subject?->plate_number ?? '—') . ')')
            : (self::driverName($subject) ?? "Driver #{$id}");

        $tripVehicle = Vehicle::find($conflict->vehicle_id);
        $when = Carbon::parse($conflict->trip_date)->format('F j, Y');
        $message = "{$subjectName} is already scheduled on {$when} at {$conflict->departure_time}"
            . ($conflict->estimated_return_time ? " – {$conflict->estimated_return_time}" : '') . '.';

        // 422 na may "conflict" details para sa conflict modal ng frontend.
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
                'driver' => self::driverName(Driver::find($conflict->driver_id)),
                'vehicle' => $tripVehicle ? trim($tripVehicle->vehicle_model . ' (' . $tripVehicle->plate_number . ')') : null,
            ],
        ], 422));
    }
}
