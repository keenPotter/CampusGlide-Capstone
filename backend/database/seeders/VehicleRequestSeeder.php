<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 1-6 completed trips | 7-8 upcoming approved | 9-10 in progress today
 * 11-12 pending | 13 disapproved
 */
class VehicleRequestSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'requester_id', 'request_date', 'trip_date', 'trip_end_date', 'trip_type',
            'departure_time', 'destination', 'purpose', 'estimated_return_time', 'passengers',
            'number_of_passengers', 'status', 'disapproval_reason',
            'approved_by', 'approved_date', 'created_at', 'updated_at',
        ];

        $requests = [
            [1,  9,  '2026-08-20 09:15:00', '2026-08-28', '2026-08-28', 'exclusive', '07:00:00', 'Tuguegarao City, Cagayan',                  'Attend DICT regional IT seminar',                '18:00:00', 'Elena Garcia, Ricardo Torres, and 3 students',          5,  'approved',    null, 1,    '2026-08-22 10:00:00', '2026-08-20 09:15:00', '2026-08-22 10:00:00'],
            [2,  10, '2026-08-25 13:40:00', '2026-09-03', '2026-09-04', 'inclusive', '06:00:00', 'Baguio City, Benguet',                      'Faculty research conference',                    '17:00:00', 'Ricardo Torres, Grace Pascual, and 4 faculty members',  6,  'approved',    null, 1,    '2026-08-28 09:30:00', '2026-08-25 13:40:00', '2026-08-28 09:30:00'],
            [3,  11, '2026-09-04 08:20:00', '2026-09-10', '2026-09-10', 'exclusive', '08:00:00', 'Provincial Capitol, Bayombong, Nueva Vizcaya', 'Submit LGU partnership documents',             '15:00:00', 'Luzviminda Castillo and Dennis Manalo',                 2,  'approved',    null, 2,    '2026-09-05 11:00:00', '2026-09-04 08:20:00', '2026-09-05 11:00:00'],
            [4,  12, '2026-09-09 10:05:00', '2026-09-15', '2026-09-15', 'exclusive', '07:30:00', 'Cabanatuan City, Nueva Ecija',              'Procurement of laboratory equipment',            '17:00:00', 'Dennis Manalo, Joel Navarro, and 1 laboratory staff',   3,  'approved',    null, 1,    '2026-09-10 14:00:00', '2026-09-09 10:05:00', '2026-09-10 14:00:00'],
            [5,  13, '2026-09-10 15:30:00', '2026-09-18', '2026-09-19', 'inclusive', '05:30:00', 'Manila, Metro Manila',                      'CHED regional coordination meeting',             '19:00:00', 'Grace Pascual and 3 college officials',                 4,  'approved',    null, 2,    '2026-09-12 09:00:00', '2026-09-10 15:30:00', '2026-09-12 09:00:00'],
            [6,  14, '2026-09-18 09:00:00', '2026-09-24', '2026-09-24', 'exclusive', '07:00:00', 'Solano, Nueva Vizcaya',                     'Student field trip to agricultural cooperative', '16:00:00', 'Joel Navarro and 13 students',                          14, 'approved',    null, 1,    '2026-09-20 10:30:00', '2026-09-18 09:00:00', '2026-09-20 10:30:00'],
            [7,  9,  '2026-09-28 11:10:00', '2026-10-06', '2026-10-06', 'exclusive', '07:00:00', 'Santiago City, Isabela',                    'Graduate school thesis defense panel',           '17:00:00', 'Elena Garcia and 2 panel members',                      3,  'approved',    null, 1,    '2026-09-30 09:45:00', '2026-09-28 11:10:00', '2026-09-30 09:45:00'],
            [8,  10, '2026-09-30 14:25:00', '2026-10-08', '2026-10-09', 'inclusive', '06:00:00', 'Vigan City, Ilocos Sur',                    'Educational tour for students',                  '18:00:00', 'Ricardo Torres and 11 students',                        12, 'approved',    null, 2,    '2026-10-01 10:00:00', '2026-09-30 14:25:00', '2026-10-01 10:00:00'],
            [9,  12, '2026-09-26 10:00:00', '2026-10-02', '2026-10-02', 'exclusive', '07:00:00', 'Ilagan City, Isabela',                      'Inter-campus sports coordination meeting',       '17:00:00', 'Dennis Manalo and 3 coaches',                           4,  'approved',    null, 1,    '2026-09-28 09:00:00', '2026-09-26 10:00:00', '2026-09-28 09:00:00'],
            [10, 13, '2026-09-27 08:45:00', '2026-10-02', '2026-10-02', 'exclusive', '07:30:00', 'Bambang, Nueva Vizcaya',                    'Delivery of instructional materials',            '15:00:00', 'Grace Pascual and 1 staff',                             2,  'approved',    null, 2,    '2026-09-29 10:15:00', '2026-09-27 08:45:00', '2026-09-29 10:15:00'],
            [11, 11, '2026-09-30 16:00:00', '2026-10-12', '2026-10-12', 'exclusive', '07:30:00', 'Dupax del Sur, Nueva Vizcaya',              'Community extension program',                    '16:30:00', 'Luzviminda Castillo and 9 extension volunteers',        10, 'pending',     null, null, null,                  '2026-09-30 16:00:00', '2026-09-30 16:00:00'],
            [12, 14, '2026-10-01 09:30:00', '2026-10-14', '2026-10-14', 'exclusive', '08:00:00', 'Aritao, Nueva Vizcaya',                     'Site visit for OJT partner establishment',       '15:30:00', 'Joel Navarro and 4 students',                           5,  'pending',     null, null, null,                  '2026-10-01 09:30:00', '2026-10-01 09:30:00'],
            [13, 9,  '2026-09-14 13:00:00', '2026-09-20', '2026-09-20', 'exclusive', '06:30:00', 'Baler, Aurora',                             'Team building activity',                         '19:00:00', 'Elena Garcia and 8 faculty members',                    9,  'disapproved', 'No available vehicle on the requested date due to scheduled maintenance and prior bookings.', null, null, '2026-09-14 13:00:00', '2026-09-16 10:00:00'],
        ];

        DB::table('vehicle_requests')->insert(
            array_map(fn (array $row) => array_combine($columns, $row), $requests)
        );
    }
}