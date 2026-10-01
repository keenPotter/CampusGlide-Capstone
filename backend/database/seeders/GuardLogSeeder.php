<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One log per request that actually departed. Guards are users 7-8.
 */
class GuardLogSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'vehicle_request_id', 'guard_id', 'vehicle_used', 'actual_departure_date',
            'actual_departure_time', 'actual_return_date', 'actual_return_time',
            'vehicle_condition_departure', 'vehicle_condition_return', 'remarks',
            'created_at', 'updated_at',
        ];

        $logs = [
            [1, 1,  7, 'NBC 5521 - Toyota Innova',         '2026-08-28', '07:12:00', '2026-08-28', '18:40:00', 'Good', 'Good',                         'Trip completed without incident.',         '2026-08-28 07:12:00', '2026-08-28 18:40:00'],
            [2, 2,  8, 'SAA 1234 - Toyota Hiace Commuter', '2026-09-03', '06:05:00', '2026-09-04', '16:50:00', 'Good', 'Good, needs washing',          'Returned the following day as scheduled.', '2026-09-03 06:05:00', '2026-09-04 16:50:00'],
            [3, 3,  7, 'AEF 6098 - Toyota Vios',           '2026-09-10', '08:05:00', '2026-09-10', '14:30:00', 'Good', 'Good',                         null,                                       '2026-09-10 08:05:00', '2026-09-10 14:30:00'],
            [4, 4,  8, 'NAB 7712 - Mitsubishi L300 FB',    '2026-09-15', '07:35:00', '2026-09-15', '17:45:00', 'Good', 'Good',                         'Returned 45 minutes past estimated time.', '2026-09-15 07:35:00', '2026-09-15 17:45:00'],
            [5, 5,  7, 'NLM 8830 - Toyota Fortuner',       '2026-09-18', '05:40:00', '2026-09-19', '18:20:00', 'Good', 'Minor scratch on rear bumper', 'Scratch reported by driver upon return.',  '2026-09-18 05:40:00', '2026-09-19 18:20:00'],
            [6, 6,  8, 'SAA 1234 - Toyota Hiace Commuter', '2026-09-24', '07:08:00', '2026-09-24', '16:20:00', 'Good', 'Good',                         null,                                       '2026-09-24 07:08:00', '2026-09-24 16:20:00'],
            [7, 9,  7, 'NBC 5521 - Toyota Innova',         '2026-10-02', '07:10:00', null,         null,       'Good', null,                           'Vehicle currently out.',                   '2026-10-02 07:10:00', '2026-10-02 07:10:00'],
            [8, 10, 8, 'NGH 2271 - Isuzu D-Max',           '2026-10-02', '07:25:00', null,         null,       'Good', null,                           'Vehicle currently out.',                   '2026-10-02 07:25:00', '2026-10-02 07:25:00'],
        ];

        DB::table('guard_logs')->insert(
            array_map(fn (array $row) => array_combine($columns, $row), $logs)
        );
    }
}