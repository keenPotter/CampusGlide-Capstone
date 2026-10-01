<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One trip per approved request, plus one cancelled trip (request 14).
 */
class TripSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'vehicle_request_id', 'vehicle_id', 'driver_id', 'trip_date', 'departure_time',
            'estimated_return_time', 'destination', 'purpose', 'trip_status',
            'actual_departure_time', 'actual_return_time', 'actual_mileage', 'notes',
            'created_at', 'updated_at',
        ];

        $trips = [
            [1,  1,  2, 2, '2026-08-28', '07:00:00', '18:00:00', 'Tuguegarao City, Cagayan',                  'Attend DICT regional IT seminar',                'completed',   '07:12:00', '18:40:00', 410,  'Slight delay due to road works.',                            '2026-08-22 10:30:00', '2026-08-28 18:45:00'],
            [2,  2,  1, 1, '2026-09-03', '06:00:00', '17:00:00', 'Baguio City, Benguet',                      'Faculty research conference',                    'completed',   '06:05:00', '16:50:00', 560,  'Two-day trip, overnight stay in Baguio.',                    '2026-08-28 10:00:00', '2026-09-04 16:55:00'],
            [3,  3,  5, 3, '2026-09-10', '08:00:00', '15:00:00', 'Provincial Capitol, Bayombong, Nueva Vizcaya', 'Submit LGU partnership documents',             'completed',   '08:05:00', '14:30:00', 38,   null,                                                         '2026-09-05 11:30:00', '2026-09-10 14:35:00'],
            [4,  4,  3, 3, '2026-09-15', '07:30:00', '17:00:00', 'Cabanatuan City, Nueva Ecija',              'Procurement of laboratory equipment',            'completed',   '07:35:00', '17:45:00', 310,  'Returned later than estimated due to loading of equipment.', '2026-09-10 14:30:00', '2026-09-15 17:50:00'],
            [5,  5,  8, 4, '2026-09-18', '05:30:00', '19:00:00', 'Manila, Metro Manila',                      'CHED regional coordination meeting',             'completed',   '05:40:00', '18:20:00', 880,  'Overnight stay in Manila.',                                  '2026-09-12 09:30:00', '2026-09-19 18:25:00'],
            [6,  6,  1, 1, '2026-09-24', '07:00:00', '16:00:00', 'Solano, Nueva Vizcaya',                     'Student field trip to agricultural cooperative', 'completed',   '07:08:00', '16:20:00', 95,   null,                                                         '2026-09-20 11:00:00', '2026-09-24 16:25:00'],
            [7,  7,  5, 3, '2026-10-06', '07:00:00', '17:00:00', 'Santiago City, Isabela',                    'Graduate school thesis defense panel',           'scheduled',   null,       null,       null, null,                                                         '2026-09-30 10:00:00', '2026-09-30 10:00:00'],
            [8,  8,  1, 1, '2026-10-08', '06:00:00', '18:00:00', 'Vigan City, Ilocos Sur',                    'Educational tour for students',                  'scheduled',   null,       null,       null, 'Two-day trip, overnight stay in Vigan.',                 '2026-10-01 10:30:00', '2026-10-01 10:30:00'],
            [9,  9,  2, 2, '2026-10-02', '07:00:00', '17:00:00', 'Ilagan City, Isabela',                      'Inter-campus sports coordination meeting',       'in_progress', '07:10:00', null,       null, null,                                                         '2026-09-28 09:30:00', '2026-10-02 07:10:00'],
            [10, 10, 6, 4, '2026-10-02', '07:30:00', '15:00:00', 'Bambang, Nueva Vizcaya',                    'Delivery of instructional materials',            'in_progress', '07:25:00', null,       null, null,                                                         '2026-09-29 10:30:00', '2026-10-02 07:25:00'],
            [11, 14, 5, 3, '2026-09-22', '07:00:00', '17:00:00', 'Roxas, Isabela',                            'Benchmarking visit to partner school',           'cancelled',   null,       null,       null, 'Cancelled by requester; event postponed.',               '2026-09-14 09:30:00', '2026-09-18 08:00:00'],
        ];

        DB::table('trips')->insert(
            array_map(fn (array $row) => array_combine($columns, $row), $trips)
        );
    }
}