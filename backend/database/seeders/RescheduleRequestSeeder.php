<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Date change requests on scheduled trips:
 * 4 pending (so testers can approve or disapprove), 1 approved, 1 disapproved.
 * Each row points to the vehicle request; the trip is found from it.
 */
class RescheduleRequestSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'trip_id', 'requested_by', 'old_date', 'new_date', 'reason', 'status',
            'handled_by', 'admin_reason', 'handled_at', 'created_at', 'updated_at',
        ];

        // [id, request id, requested by, old day, new day, reason, status, handled by, admin note, sent day, handled day]
        $requests = [
            [1, 8,  8, 9,  11, 'The academic council meeting was moved to a later date.',          'pending',     null, null, -1, null],
            [2, 28, 5, 15, 17, 'The DepEd officials asked to move the meeting by two days.',        'pending',     null, null, -1, null],
            [3, 29, 6, 16, 19, 'The municipal mayor asked to move the meeting to a later date.',    'pending',     null, null, -1, null],
            [4, 30, 7, 17, 22, 'The hospital administration moved the coordination meeting.',       'pending',     null, null, -1, null],
            // The trip of request 9 already moved to its new date (12).
            [5, 9,  7, 11, 12, 'The provincial board moved the presentation to the next day.',      'approved',    1,    'Approved. The vehicle and driver are free on the new date.',                  -4, -3],
            [6, 13, 5, 14, 15, 'The department heads asked to move the meeting by one day.',        'disapproved', 2,    'The motorpool cannot move this trip. Please keep the original date.',          -3, -2],
        ];

        $rows = array_map(function (array $r) use ($columns) {
            $tripId = DB::table('trips')->where('vehicle_request_id', $r[1])->value('id');
            $sent = SeedDates::at($r[9], '09:00:00');
            $handled = $r[10] === null ? null : SeedDates::at($r[10], '14:00:00');

            return array_combine($columns, [
                $r[0], $tripId, $r[2], SeedDates::date($r[3]), SeedDates::date($r[4]), $r[5], $r[6],
                $r[7], $r[8], $handled, $sent, $handled ?? $sent,
            ]);
        }, $requests);

        DB::table('reschedule_requests')->insert($rows);
    }
}