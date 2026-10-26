<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * end_trip_balance = balance_in_tank + issuance_from_stock + fuel_purchased - fuel_used
 * (it is calculated here, so it is always correct).
 * Records 1-5 belong to the completed trips (requests 1, 2, 3, 4, 27). Records 6-7 are not tied to a trip.
 */
class FuelUsageRecordSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'vehicle_id', 'trip_id', 'record_date', 'balance_in_tank', 'issuance_from_stock',
            'fuel_purchased', 'fuel_used', 'end_trip_balance', 'riv_no', 'riv_date', 'or_no', 'or_date',
            'lubricating_oil', 'diesel_water', 'gear_oil', 'brake_fluid', 'flushing_oil', 'grease',
            'drivers', 'created_at', 'updated_at',
        ];

        $year = Carbon::today()->year;
        $riv = fn (int $n) => "RIV-{$year}-{$n}";
        $d = fn (?int $n) => $n === null ? null : SeedDates::date($n);

        // [id, vehicle, request id, day, balance, issuance, purchased, used, RIV no, RIV day, OR no, OR day, lubricating oil, driver]
        $records = [
            [1, 2, 1,    -24, 18.00, 20.00, 0.00,  5.00,  $riv(210), -25, null,        null, null, 'Pedro Bautista'],
            [2, 8, 2,    -18, 45.00, 40.00, 30.00, 77.00, $riv(214), -21, 'OR-612045', -20,  1.00, 'Ernesto Villanueva'],
            [3, 5, 3,    -12, 20.00, 0.00,  0.00,  2.50,  null,      null, null,        null, null, 'Carlos Mendoza'],
            [4, 1, 4,    -8,  22.00, 15.00, 0.00,  8.00,  $riv(226), -9,  null,        null, null, 'Juan Reyes'],
            [5, 5, 27,   -5,  15.00, 0.00,  20.00, 6.00,  null,      null, 'OR-613402', -5,   null, 'Armando Cabrera'],
            [6, 3, null, -14, 10.00, 20.00, 0.00,  4.00,  $riv(233), -15, null,        null, null, 'Carlos Mendoza'],
            [7, 6, null, -11, 12.00, 0.00,  40.00, 5.00,  null,      null, 'OR-613380', -11,  null, 'Ernesto Villanueva'],
        ];

        $rows = array_map(function (array $r) use ($columns, $d) {
            $tripId = $r[2] === null ? null : DB::table('trips')->where('vehicle_request_id', $r[2])->value('id');
            $filed = SeedDates::at($r[3], '16:00:00');

            return array_combine($columns, [
                $r[0], $r[1], $tripId, $d($r[3]), $r[4], $r[5], $r[6], $r[7],
                round($r[4] + $r[5] + $r[6] - $r[7], 2),
                $r[8], $d($r[9]), $r[10], $d($r[11]),
                $r[12], null, null, null, null, null,
                $r[13], $filed, $filed,
            ]);
        }, $records);

        DB::table('fuel_usage_records')->insert($rows);
    }
}