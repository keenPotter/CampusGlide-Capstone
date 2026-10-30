<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Covers every status shown on the Maintenance page:
 * in progress (3), upcoming (1, 2, 5, 6, 8, 9, 11), overdue (4), cancelled (10), completed (7).
 * Record 9 is on the same date as trip 13 (request 13), which needs reassignment.
 * Records 12-16 are extra scheduled records on vehicle 4. That vehicle is not used in any trip,
 * so they never block a trip. Together with records 3, 9 and 11 there are enough
 * records for each administrator tester to mark one as completed (TC-AD-15).
 */
class VehicleMaintenanceSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'vehicle_id', 'maintenance_type', 'description', 'maintenance_date',
            'completion_date', 'cost', 'next_due_date', 'performed_by', 'status', 'notes',
            'created_at', 'updated_at',
        ];

        $d = fn (?int $n) => $n === null ? null : SeedDates::date($n);

        // [id, vehicle, type, description, date, completion, cost, next due, performed by, status, notes, last update day]
        $records = [
            [1,  1, 'oil_change',   'Engine oil and oil filter replacement',      -78,  -78,  3500.00,  105,  'Toyota Santiago Service Center', 'completed',   null, null],
            [2,  2, 'tire_service', 'Replaced four tires and wheel alignment',    -103, -103, 18400.00, 262,  'Bayombong Tire Center',          'completed',   null, null],
            [3,  4, 'repair',       'Clutch assembly replacement',                -31,  null, 32000.00, null, 'Dela Cruz Auto Repair',          'in_progress', 'Waiting for delivery of clutch disc.', -3],
            // The next due date already passed, so it shows as overdue.
            [4,  3, 'inspection',   'Semi-annual general inspection',             -128, -128, 1200.00,  -6,   'NVSU Motor Pool Mechanic',       'completed',   null, null],
            [5,  5, 'oil_change',   'Engine oil and filter replacement',          -50,  -50,  2800.00,  130,  'Toyota Santiago Service Center', 'completed',   null, null],
            [6,  6, 'oil_change',   'Engine oil and filter replacement',          -62,  -62,  5200.00,  120,  'Isuzu Cauayan Service Center',   'completed',   null, null],
            [7,  7, 'repair',       'Engine overhaul',                            -230, -230, 45000.00, null, 'Dela Cruz Auto Repair',          'completed',   'Unit recommended for retirement after overhaul.', null],
            [8,  8, 'oil_change',   'First free service - oil and filter change', -43,  -43,  4100.00,  139,  'Toyota Santiago Service Center', 'completed',   null, null],
            [9,  1, 'tire_service', 'Tire rotation and balancing',                14,   null, null,     null, null,                             'scheduled',   null, null],
            [10, 3, 'other',        'Air conditioning servicing',                 -175, null, null,     null, null,                             'cancelled',   'Rescheduled due to parts unavailability.', -175],
            [11, 6, 'inspection',   'Pre-long-trip safety inspection',            9,    null, null,     null, 'NVSU Motor Pool Mechanic',       'scheduled',   null, null],
            // Extra records for the testers (vehicle 4 is never assigned to a trip).
            [12, 4, 'inspection',   'Post-repair safety inspection',              12,   null, null,     null, 'NVSU Motor Pool Mechanic',       'scheduled',   null, null],
            [13, 4, 'tire_service', 'Tire replacement and wheel alignment',       16,   null, null,     null, 'Bayombong Tire Center',          'scheduled',   null, null],
            [14, 4, 'oil_change',   'Engine oil and filter replacement',          21,   null, null,     null, 'Toyota Santiago Service Center', 'scheduled',   null, null],
            [15, 4, 'other',        'Air conditioning servicing',                 27,   null, null,     null, 'Dela Cruz Auto Repair',          'scheduled',   null, null],
            [16, 4, 'inspection',   'Monthly safety inspection',                  33,   null, null,     null, 'NVSU Motor Pool Mechanic',       'scheduled',   null, null],
        ];

        $rows = array_map(function (array $r) use ($columns, $d) {
            $created = min($r[4] - 4, -1);
            $updated = $r[11] ?? $r[5] ?? $created;

            return array_combine($columns, [
                $r[0], $r[1], $r[2], $r[3], $d($r[4]), $d($r[5]), $r[6], $d($r[7]), $r[8], $r[9], $r[10],
                SeedDates::at($created, '09:00:00'), SeedDates::at($updated, '15:00:00'),
            ]);
        }, $records);

        DB::table('vehicle_maintenance')->insert($rows);
    }
}