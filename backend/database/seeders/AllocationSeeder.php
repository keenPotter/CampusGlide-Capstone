<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One allocation record per trip: who assigned which vehicle and driver, and when.
 * The vehicle and driver are the same as on the trip. The administrator and the date
 * are the ones who approved the request.
 * The RUV number is filled in for trips whose form was already printed
 * (completed and in progress). The next printed form continues from the last number.
 */
class AllocationSeeder extends Seeder
{
    // request_id => note
    private const NOTES = [
        2 => 'Overnight stay in Quezon City.',
        7 => 'Overnight stay in Manila.',
        8 => 'Passengers will gather at the main gate.',
    ];

    public function run(): void
    {
        $trips = DB::table('trips')
            ->join('vehicle_requests as r', 'r.id', '=', 'trips.vehicle_request_id')
            ->orderBy('trips.id')
            ->get([
                'trips.id', 'trips.vehicle_id', 'trips.driver_id', 'trips.trip_status',
                'trips.vehicle_request_id', 'r.approved_by', 'r.approved_date',
            ]);

        $year = Carbon::today()->year;
        $printed = 0;
        $rows = [];

        foreach ($trips as $trip) {
            $ruv = null;

            if (in_array($trip->trip_status, ['completed', 'in_progress'], true)) {
                $printed++;
                $ruv = sprintf('%d-%04d', $year, $printed);
            }

            $rows[] = [
                'id' => $trip->id,
                'trip_id' => $trip->id,
                'vehicle_id' => $trip->vehicle_id,
                'driver_id' => $trip->driver_id,
                'allocated_by' => $trip->approved_by,
                'allocation_date' => $trip->approved_date,
                'notes' => self::NOTES[$trip->vehicle_request_id] ?? null,
                'ruv_number' => $ruv,
                'created_at' => $trip->approved_date,
                'updated_at' => $trip->approved_date,
            ];
        }

        DB::table('allocations')->insert($rows);
    }
}