<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reports 1-5 belong to the completed trips (requests 1, 2, 3, 4, 27). Report 6 is not tied to a trip.
 */
class PostTravelReportSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'vehicle_id', 'trip_id', 'travel_date_from', 'travel_date_to', 'places_of_travel',
            'defects_observed', 'defects_incurred', 'remarks', 'drivers', 'arrival_at',
            'created_at', 'updated_at',
        ];

        // [id, vehicle, request id, first day, last day, places, observed, incurred, remarks, drivers, arrival time]
        $reports = [
            [1, 2, 1,    -24, -24,  'Bambang - Bayombong - Bambang',      null,                                null, 'Smooth trip. Vehicle in good running condition.',            'Pedro Bautista',     '15:40:00'],
            [2, 8, 2,    -19, -18,  'Bayombong - Quezon City - Bayombong', 'Slight vibration at high speed.',   null, 'Overnight stay in Quezon City. Wheel alignment recommended.', 'Ernesto Villanueva', '19:30:00'],
            [3, 5, 3,    -12, -12,  'Solano - Bayombong - Solano',        null,                                null, null,                                                         'Carlos Mendoza',     '14:20:00'],
            [4, 1, 4,    -8,  -8,   'Bambang - Solano - Bambang',         'Slight brake noise when stopping.', null, 'Brake inspection recommended before the next long trip.',    'Juan Reyes',         '16:40:00'],
            [5, 5, 27,   -5,  -5,   'Bayombong - Congressman\'s office - Bayombong', null,                     null, null,                                                         'Armando Cabrera',    '14:30:00'],
            [6, 3, null, -14, null, 'Local errands within Bayombong',     null,                                null, null,                                                         'Carlos Mendoza',     '15:30:00'],
        ];

        $rows = array_map(function (array $r) use ($columns) {
            $tripId = $r[2] === null ? null : DB::table('trips')->where('vehicle_request_id', $r[2])->value('id');
            $lastDay = $r[4] ?? $r[3];
            $arrival = SeedDates::at($lastDay, $r[10]);
            $filed = SeedDates::at($lastDay, date('H:i:s', strtotime($r[10]) + 1200));

            return array_combine($columns, [
                $r[0], $r[1], $tripId, SeedDates::date($r[3]), $r[4] === null ? null : SeedDates::date($r[4]),
                $r[5], $r[6], $r[7], $r[8], $r[9], $arrival, $filed, $filed,
            ]);
        }, $reports);

        DB::table('post_travel_reports')->insert($rows);
    }
}