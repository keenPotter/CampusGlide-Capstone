<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostTravelReportSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'vehicle_id', 'trip_id', 'travel_date_from', 'travel_date_to', 'places_of_travel',
            'defects_observed', 'defects_incurred', 'remarks', 'drivers', 'arrival_at',
            'created_at', 'updated_at',
        ];

        $reports = [
            [1, 2, 1,    '2026-08-28', '2026-08-28', 'Bayombong - Tuguegarao City - Bayombong', null,                                   null,                           'Smooth trip. Vehicle in good running condition.',     'Pedro Bautista',     '2026-08-28 18:40:00', '2026-08-28 19:00:00', '2026-08-28 19:00:00'],
            [2, 1, 2,    '2026-09-03', '2026-09-04', 'Bayombong - Baguio City - Bayombong',    'Slight brake noise on steep descent.', null,                           'Brake inspection recommended before next long trip.', 'Juan Reyes',         '2026-09-04 16:50:00', '2026-09-04 17:15:00', '2026-09-04 17:15:00'],
            [3, 3, 4,    '2026-09-15', '2026-09-15', 'Bayombong - Cabanatuan City - Bayombong', null,                                  null,                           'Heavy cargo loaded on return trip.',                  'Carlos Mendoza',     '2026-09-15 17:45:00', '2026-09-15 18:10:00', '2026-09-15 18:10:00'],
            [4, 8, 5,    '2026-09-18', '2026-09-19', 'Bayombong - Manila - Bayombong',         null,                                   'Minor scratch on rear bumper', 'Scratch occurred while parking in Manila.',           'Ernesto Villanueva', '2026-09-19 18:20:00', '2026-09-19 18:45:00', '2026-09-19 18:45:00'],
            [5, 1, 6,    '2026-09-24', '2026-09-24', 'Bayombong - Solano - Bayombong',         'Air conditioning not cooling well.',   null,                           'AC check to be included in next scheduled service.',  'Juan Reyes',         '2026-09-24 16:20:00', '2026-09-24 16:40:00', '2026-09-24 16:40:00'],
            [6, 3, null, '2026-08-14', null,         'Local errands within Bayombong',         null,                                   null,                           null,                                                  'Carlos Mendoza',     '2026-08-14 15:30:00', '2026-08-14 15:45:00', '2026-08-14 15:45:00'],
        ];

        DB::table('post_travel_reports')->insert(
            array_map(fn (array $row) => array_combine($columns, $row), $reports)
        );
    }
}