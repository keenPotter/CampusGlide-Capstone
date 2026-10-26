<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * OPTIONAL. Adds 150 more vehicle requests for the ISO 25010 tests on
 * Performance Efficiency (time behaviour, capacity) and for checking pagination.
 *
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=BulkRequestSeeder
 *
 *  80 approved requests with completed trips and allocations (ids 1001-1080), 35 to 114 days ago
 *  40 disapproved requests (ids 1101-1140), in the past
 *  30 pending requests (ids 1201-1230), 25 to 54 days from now
 *
 * No random values are used, so every run gives the same data.
 * The bulk trips are all in the past, so they never block the main test data.
 * The allocation page loads at most 100 allocations, and the 80 here
 * plus the 17 main trips stay under that limit.
 */
class BulkRequestSeeder extends Seeder
{
    private const VEHICLES = [1, 2, 3, 5, 6, 8];
    private const CAPACITY = [15, 7, 3, 4, 5, 7];
    private const DRIVERS = [1, 2, 3, 4, 6];

    private const LEADS = [
        4 => 'Wilfredo Dumale Jr.',
        5 => 'Marife Aliaga',
        6 => 'Krizsha Ellaine Salinas',
        7 => 'Dennis Directo',
        8 => 'Gwen Kyle Gagasa',
    ];

    private const INCLUSIVE = [
        ['NVSU Main Campus, Bayombong, Nueva Vizcaya', 'Faculty meeting on instruction and curriculum'],
        ['Provincial Capitol, Bayombong, Nueva Vizcaya', 'Coordination meeting with provincial officials'],
        ['Municipal Hall, Bambang, Nueva Vizcaya', 'Meeting with municipal officials on community extension'],
        ['Municipal Hall, Solano, Nueva Vizcaya', 'Meeting on the barangay training program'],
        ['Provincial Health Office, Bayombong, Nueva Vizcaya', 'Meeting on the nursing students community program'],
        ['DepEd Schools Division Office, Bayombong, Nueva Vizcaya', 'Meeting on the senior high school partnership'],
        ['DTI Provincial Office, Bayombong, Nueva Vizcaya', 'Meeting on the entrepreneurship seminar'],
        ['Office of the Congressman, Bayombong, Nueva Vizcaya', 'Meeting with the congressman and staff on student programs'],
    ];

    private const EXCLUSIVE = [
        ['CHED Central Office, Quezon City, Metro Manila', 'Meeting with CHED officials on program accreditation'],
        ['DOST Central Office, Bicutan, Taguig City, Metro Manila', 'Meeting on research funding'],
        ['Department of Budget and Management, Manila', 'Meeting on the proposed university budget'],
        ['Baguio City, Benguet', 'Regional conference of state universities and colleges'],
    ];

    private const REASONS = [
        'The vehicle needed for this trip is under maintenance on the requested date.',
        'No available driver or vehicle on the requested date.',
        'The requested date is already fully booked. Please choose another date.',
        'The requested date is a university holiday.',
    ];

    public function run(): void
    {
        if (DB::table('vehicle_requests')->where('id', 1001)->exists()) {
            $this->command?->warn('Bulk data is already loaded. Run "php artisan migrate:fresh --seed" first.');

            return;
        }

        DB::transaction(function () {
            $this->completed();
            $this->disapproved();
            $this->pending();
        });

        $this->command?->info('Bulk data added: 80 approved, 40 disapproved and 30 pending requests.');
    }

    private function completed(): void
    {
        for ($i = 0; $i < 80; $i++) {
            $exclusive = $i % 9 === 8;
            $by = $exclusive ? 4 : 5 + $i % 4;
            $start = $i - 114;
            $end = $exclusive ? $start + 1 : $start;
            $slot = $i % 6;
            $count = 1 + ($i * 7) % min(self::CAPACITY[$slot], 8);
            [$destination, $purpose] = $exclusive ? self::EXCLUSIVE[intdiv($i, 9) % 4] : self::INCLUSIVE[$i % 8];
            $depart = $exclusive ? '05:00:00' : sprintf('%02d:00:00', 7 + $i % 3);
            $return = $exclusive ? '20:00:00' : sprintf('%02d:00:00', 15 + $i % 4);
            $approver = 1 + $i % 2;
            $sent = SeedDates::at($start - 5, '09:00:00');
            $decided = SeedDates::at($start - 3, '10:00:00');
            $id = 1001 + $i;

            DB::table('vehicle_requests')->insert([
                'id' => $id,
                'requester_id' => $by,
                'request_date' => $sent,
                'trip_date' => SeedDates::date($start),
                'trip_end_date' => SeedDates::date($end),
                'trip_type' => $exclusive ? 'exclusive' : 'inclusive',
                'departure_time' => $depart,
                'destination' => $destination,
                'purpose' => $purpose,
                'estimated_return_time' => $return,
                'passengers' => $this->passengers($by, $count),
                'number_of_passengers' => $count,
                'status' => 'approved',
                'disapproval_reason' => null,
                'edit_reason' => null,
                'approved_by' => $approver,
                'approved_date' => $decided,
                'created_at' => $sent,
                'updated_at' => $decided,
            ]);

            $actualReturn = date('H:i:s', strtotime($return) - 1200);

            DB::table('trips')->insert([
                'id' => $id,
                'vehicle_request_id' => $id,
                'vehicle_id' => self::VEHICLES[$slot],
                'driver_id' => self::DRIVERS[$i % 5],
                'trip_date' => SeedDates::date($start),
                'departure_time' => $depart,
                'estimated_return_time' => $return,
                'destination' => $destination,
                'purpose' => $purpose,
                'trip_status' => 'completed',
                'actual_departure_time' => date('H:i:s', strtotime($depart) + 300),
                'actual_return_time' => $actualReturn,
                'actual_mileage' => $exclusive ? 650 + $i : 20 + ($i * 13) % 80,
                'notes' => null,
                'created_at' => $decided,
                'updated_at' => SeedDates::at($end, $actualReturn),
            ]);

            DB::table('allocations')->insert([
                'id' => $id,
                'trip_id' => $id,
                'vehicle_id' => self::VEHICLES[$slot],
                'driver_id' => self::DRIVERS[$i % 5],
                'allocated_by' => $approver,
                'allocation_date' => $decided,
                'notes' => null,
                'ruv_number' => null,
                'created_at' => $decided,
                'updated_at' => $decided,
            ]);
        }
    }

    private function disapproved(): void
    {
        $rows = [];

        for ($i = 0; $i < 40; $i++) {
            $by = 5 + $i % 4;
            $start = -(10 + 2 * $i);
            [$destination, $purpose] = self::INCLUSIVE[($i + 3) % 8];
            $sent = SeedDates::at($start - 5, '09:30:00');
            $decided = SeedDates::at($start - 3, '10:00:00');

            $rows[] = [
                'id' => 1101 + $i,
                'requester_id' => $by,
                'request_date' => $sent,
                'trip_date' => SeedDates::date($start),
                'trip_end_date' => SeedDates::date($start),
                'trip_type' => 'inclusive',
                'departure_time' => '08:00:00',
                'destination' => $destination,
                'purpose' => $purpose,
                'estimated_return_time' => '16:00:00',
                'passengers' => $this->passengers($by, 2 + $i % 6),
                'number_of_passengers' => 2 + $i % 6,
                'status' => 'disapproved',
                'disapproval_reason' => self::REASONS[$i % 4],
                'edit_reason' => null,
                'approved_by' => 1 + $i % 2,
                'approved_date' => null,
                'created_at' => $sent,
                'updated_at' => $decided,
            ];
        }

        DB::table('vehicle_requests')->insert($rows);
    }

    private function pending(): void
    {
        $rows = [];

        for ($i = 0; $i < 30; $i++) {
            $exclusive = $i % 8 === 7;
            $by = $exclusive ? 4 : 5 + $i % 4;
            $start = 25 + $i;
            $count = $exclusive ? 3 : 1 + $i % 6;
            [$destination, $purpose] = $exclusive ? self::EXCLUSIVE[intdiv($i, 8) % 4] : self::INCLUSIVE[($i + 5) % 8];
            $sent = SeedDates::at(-(1 + $i % 5), '11:00:00');

            $rows[] = [
                'id' => 1201 + $i,
                'requester_id' => $by,
                'request_date' => $sent,
                'trip_date' => SeedDates::date($start),
                'trip_end_date' => SeedDates::date($exclusive ? $start + 1 : $start),
                'trip_type' => $exclusive ? 'exclusive' : 'inclusive',
                'departure_time' => $exclusive ? '05:00:00' : '08:00:00',
                'destination' => $destination,
                'purpose' => $purpose,
                'estimated_return_time' => $exclusive ? '20:00:00' : '16:00:00',
                'passengers' => $this->passengers($by, $count),
                'number_of_passengers' => $count,
                'status' => 'pending',
                'disapproval_reason' => null,
                'edit_reason' => null,
                'approved_by' => null,
                'approved_date' => null,
                'created_at' => $sent,
                'updated_at' => $sent,
            ];
        }

        DB::table('vehicle_requests')->insert($rows);
    }

    private function passengers(int $by, int $count): string
    {
        $lead = self::LEADS[$by];

        return match (true) {
            $count <= 1 => $lead,
            $count === 2 => "{$lead} and 1 faculty member",
            default => $lead . ' and ' . ($count - 1) . ' faculty members',
        };
    }
}