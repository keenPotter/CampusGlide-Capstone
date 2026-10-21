<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Date change requests on scheduled trips, one for each status:
 * 1 pending (waiting for the administrator), 2 approved, 3 disapproved.
 */
class RescheduleRequestSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'trip_id', 'requested_by', 'old_date', 'new_date', 'reason', 'status',
            'handled_by', 'admin_reason', 'handled_at', 'created_at', 'updated_at',
        ];

        $requests = [
            [1, 8,  8, '2026-10-21', '2026-10-23', 'The academic council meeting was moved to a later date.',         'pending',     null, null,                                                                      null,                  '2026-10-12 09:00:00', '2026-10-12 09:00:00'],
            [2, 9,  7, '2026-10-21', '2026-10-22', 'The provincial board moved the presentation to the next day.',    'approved',    1,    'Approved. The vehicle and driver are free on the new date.',              '2026-10-12 14:00:00', '2026-10-12 10:00:00', '2026-10-12 14:00:00'],
            [3, 10, 5, '2026-10-20', '2026-10-21', 'The department heads asked to move the meeting by one day.',      'disapproved', 2,    'The only vehicle that fits this group is already booked on the new date.', '2026-10-14 11:00:00', '2026-10-13 15:00:00', '2026-10-14 11:00:00'],
        ];

        DB::table('reschedule_requests')->insert(
            array_map(fn (array $row) => array_combine($columns, $row), $requests)
        );
    }
}