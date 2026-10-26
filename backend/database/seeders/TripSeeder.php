<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One trip per approved request. The trip details (date, times, destination, purpose)
 * are copied from the request, so the two always match.
 * The vehicle and driver are assigned when the request is approved,
 * so every approved request already has a trip.
 *
 * Trip numbers follow the order below (1 = request 1, ... 17 = request 32).
 * No vehicle or driver is booked twice on the same day, except the intended
 * "needs reassignment" case (request 13: vehicle 1 has maintenance that day).
 */
class TripSeeder extends Seeder
{
    /**
     * request_id => [vehicle_id, driver_id, status, actual departure, actual return, mileage, notes]
     */
    private const ASSIGNMENTS = [
        1  => [2, 2, 'completed',   '08:05:00', '15:40:00', 45,   null],
        2  => [8, 4, 'completed',   '05:10:00', '19:30:00', 690,  'Two-day trip, overnight stay in Quezon City.'],
        3  => [5, 3, 'completed',   '09:05:00', '14:20:00', 24,   null],
        4  => [1, 1, 'completed',   '08:35:00', '16:40:00', 62,   null],
        27 => [5, 6, 'completed',   '08:05:00', '14:30:00', 31,   null],
        5  => [2, 2, 'in_progress', '07:35:00', null,       null, 'Week-long monitoring. The vehicle goes out every day.'],
        6  => [6, 4, 'in_progress', '08:05:00', null,       null, null],
        26 => [8, 6, 'scheduled',   null,       null,       null, 'Week-long trip. Overnight stays in Metro Manila and Baguio City.'],
        7  => [8, 1, 'scheduled',   null,       null,       null, 'Two-day trip, overnight stay in Manila.'],
        8  => [1, 3, 'scheduled',   null,       null,       null, null],
        9  => [5, 3, 'scheduled',   null,       null,       null, null],
        13 => [1, 1, 'scheduled',   null,       null,       null, null],
        28 => [3, 2, 'scheduled',   null,       null,       null, null],
        29 => [5, 3, 'scheduled',   null,       null,       null, null],
        30 => [1, 4, 'scheduled',   null,       null,       null, null],
        31 => [2, 6, 'scheduled',   null,       null,       null, null],
        32 => [1, 1, 'scheduled',   null,       null,       null, null],
    ];

    public function run(): void
    {
        $requests = DB::table('vehicle_requests')
            ->whereIn('id', array_keys(self::ASSIGNMENTS))
            ->get()
            ->keyBy('id');

        $rows = [];
        $id = 0;

        foreach (self::ASSIGNMENTS as $requestId => [$vehicleId, $driverId, $status, $departed, $returned, $mileage, $notes]) {
            $request = $requests[$requestId];
            $id++;

            $updated = match ($status) {
                'completed' => "{$request->trip_end_date} {$returned}",
                'in_progress' => "{$request->trip_date} {$departed}",
                default => $request->approved_date,
            };

            $rows[] = [
                'id' => $id,
                'vehicle_request_id' => $requestId,
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'trip_date' => $request->trip_date,
                'departure_time' => $request->departure_time,
                'estimated_return_time' => $request->estimated_return_time,
                'destination' => $request->destination,
                'purpose' => $request->purpose,
                'trip_status' => $status,
                'actual_departure_time' => $departed,
                'actual_return_time' => $returned,
                'actual_mileage' => $mileage,
                'notes' => $notes,
                'created_at' => $request->approved_date,
                'updated_at' => $updated,
            ];
        }

        DB::table('trips')->insert($rows);
    }
}