<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Spare data for the UAT, so that 4 or more testers can test each role at the same time
 * without using up each other's records.
 *
 * Faculty testers (users 18-22): each one has 5 requests of their own
 *   1 completed trip, 2 scheduled trips (no date change request yet), 1 pending, 1 disapproved
 *   Request ids 2001-2025.
 * Administrator tester pool:
 *   12 pending requests to approve or disapprove (ids 2101-2112), on free dates
 *   4 pending requests on the same dates as existing trips (ids 2201-2204), for the "Unavailable" check
 * Date change pool (staff and administrator, TC-ST-07 and TC-AD-10):
 *   8 more pending date change requests on scheduled trips (request ids 2301-2308)
 * Reassign pool (TC-ST-05, TC-ST-06, TC-AD-09):
 *   10 more scheduled trips (request ids 2401-2410)
 *
 * Every new trip is on its own day, so no vehicle or driver is booked twice. Driver 3 (license
 * expires soon) and driver 5 (on leave) are not used. Only vehicles with room for 4 passengers
 * or more are used. The main test data (requests 1-35) is not changed.
 * No random values are used, so every run gives the same data.
 */
class UatTesterSeeder extends Seeder
{
    private const VEHICLES = [1, 2, 5, 6, 8];
    private const DRIVERS = [1, 2, 4, 6];

    private const LEADS = [
        5 => 'Marife Aliaga',
        6 => 'Krizsha Ellaine Salinas',
        7 => 'Dennis Directo',
        8 => 'Gwen Kyle Gagasa',
        18 => 'Test Faculty One',
        19 => 'Test Faculty Two',
        20 => 'Test Faculty Three',
        21 => 'Test Faculty Four',
        22 => 'Test Faculty Five',
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

    private const REASONS = [
        'The vehicle needed for this trip is under maintenance on the requested date.',
        'No available driver or vehicle on the requested date.',
        'The requested date is already fully booked. Please choose another date.',
        'The requested date is a university holiday.',
    ];

    private const CHANGE_REASONS = [
        'The meeting was moved to the next day by the host office.',
        'The officials asked to move the meeting by one day.',
        'The schedule of the guest speaker changed.',
        'The department asked to move the activity to the next day.',
    ];

    private int $seq = 0;

    public function run(): void
    {
        $this->facultyTesters();
        $this->approvalPool();
        $this->conflictPool();
        $this->reschedulePool();
        $this->reassignPool();

        $this->command?->info('UAT tester data added.');
    }

    /**
     * Users 18-22: 1 completed, 2 scheduled, 1 pending, 1 disapproved each.
     */
    private function facultyTesters(): void
    {
        for ($t = 0; $t < 5; $t++) {
            $user = 18 + $t;
            $base = 2001 + $t * 5;

            $this->approved($base, $user, -30 - $t, 'completed');
            $this->approved($base + 1, $user, 30 + $t * 2, 'scheduled');
            $this->approved($base + 2, $user, 31 + $t * 2, 'scheduled');
            $this->pending($base + 3, $user, 75 + $t);
            $this->disapproved($base + 4, $user, -(20 + $t), $t);
        }
    }

    /**
     * Pending requests on free dates. Approving one books a vehicle and driver on its own day only.
     */
    private function approvalPool(): void
    {
        for ($k = 0; $k < 12; $k++) {
            $this->pending(2101 + $k, 5 + $k % 4, 60 + $k);
        }
    }

    /**
     * Pending requests on the same dates as trips 7, 9, 31 and 32,
     * so the Vehicle and Driver lists show "Unavailable" with the reason.
     */
    private function conflictPool(): void
    {
        $days = [10, 12, 18, 19];

        foreach ($days as $k => $day) {
            $this->pending(2201 + $k, 5 + $k % 4, $day);
        }
    }

    /**
     * Scheduled trips that already have a pending date change request (new date = next day).
     */
    private function reschedulePool(): void
    {
        $sent = SeedDates::at(-1, '09:00:00');

        for ($k = 0; $k < 8; $k++) {
            $id = 2301 + $k;
            $by = 5 + $k % 4;
            $old = 90 + 2 * $k;

            $this->approved($id, $by, $old, 'scheduled');

            DB::table('reschedule_requests')->insert([
                'id' => 7 + $k,
                'trip_id' => $id,
                'requested_by' => $by,
                'old_date' => SeedDates::date($old),
                'new_date' => SeedDates::date($old + 1),
                'reason' => self::CHANGE_REASONS[$k % 4],
                'status' => 'pending',
                'handled_by' => null,
                'admin_reason' => null,
                'handled_at' => null,
                'created_at' => $sent,
                'updated_at' => $sent,
            ]);
        }
    }

    /**
     * Scheduled trips with no date change request, to use for Reassign.
     */
    private function reassignPool(): void
    {
        for ($k = 0; $k < 10; $k++) {
            $this->approved(2401 + $k, 5 + $k % 4, 40 + $k, 'scheduled');
        }
    }

    /**
     * Approved request + trip + allocation. The trip id is the same as the request id.
     *
     * @param  int  $day  trip day, counted from today
     * @param  string  $status  'scheduled' or 'completed'
     */
    private function approved(int $id, int $by, int $day, string $status): void
    {
        $slot = $this->seq++;
        $vehicle = self::VEHICLES[$slot % 5];
        $driver = self::DRIVERS[$slot % 4];
        [$destination, $purpose] = self::INCLUSIVE[$slot % 8];
        $count = 3 + $slot % 2;
        $approver = 1 + $slot % 2;
        $completed = $status === 'completed';

        // The request is always sent and decided in the past, even when the trip is far ahead.
        $sent = SeedDates::at(min($day - 8, -3), '09:00:00');
        $decided = SeedDates::at(min($day - 6, -2), '10:00:00');
        $actualReturn = '15:40:00';

        DB::table('vehicle_requests')->insert(
            $this->requestRow($id, $by, $sent, $day, $destination, $purpose, $count, 'approved', null, $approver, $decided, $decided)
        );

        DB::table('trips')->insert([
            'id' => $id,
            'vehicle_request_id' => $id,
            'vehicle_id' => $vehicle,
            'driver_id' => $driver,
            'trip_date' => SeedDates::date($day),
            'departure_time' => '08:00:00',
            'estimated_return_time' => '16:00:00',
            'destination' => $destination,
            'purpose' => $purpose,
            'trip_status' => $status,
            'actual_departure_time' => $completed ? '08:05:00' : null,
            'actual_return_time' => $completed ? $actualReturn : null,
            'actual_mileage' => $completed ? 30 + $slot : null,
            'notes' => null,
            'created_at' => $decided,
            'updated_at' => $completed ? SeedDates::at($day, $actualReturn) : $decided,
        ]);

        // Completed trips were already printed, so they continue the RUV numbers of the main data.
        $ruv = $completed
            ? sprintf('%d-%04d', Carbon::today()->year, DB::table('allocations')->whereNotNull('ruv_number')->count() + 1)
            : null;

        DB::table('allocations')->insert([
            'id' => $id,
            'trip_id' => $id,
            'vehicle_id' => $vehicle,
            'driver_id' => $driver,
            'allocated_by' => $approver,
            'allocation_date' => $decided,
            'notes' => null,
            'ruv_number' => $ruv,
            'created_at' => $decided,
            'updated_at' => $decided,
        ]);
    }

    private function pending(int $id, int $by, int $day): void
    {
        [$destination, $purpose] = self::INCLUSIVE[($id + 3) % 8];
        $count = 2 + $id % 3;
        $sent = SeedDates::at(-(1 + $id % 3), '10:00:00');

        DB::table('vehicle_requests')->insert(
            $this->requestRow($id, $by, $sent, $day, $destination, $purpose, $count, 'pending', null, null, null, $sent)
        );
    }

    private function disapproved(int $id, int $by, int $day, int $k): void
    {
        [$destination, $purpose] = self::INCLUSIVE[($id + 5) % 8];
        $count = 2 + $id % 3;
        $sent = SeedDates::at($day - 5, '09:30:00');
        $decided = SeedDates::at($day - 3, '10:00:00');

        DB::table('vehicle_requests')->insert(
            $this->requestRow($id, $by, $sent, $day, $destination, $purpose, $count, 'disapproved', self::REASONS[$k % 4], 1 + $id % 2, null, $decided)
        );
    }

    private function requestRow(
        int $id,
        int $by,
        string $sent,
        int $day,
        string $destination,
        string $purpose,
        int $count,
        string $status,
        ?string $reason,
        ?int $approver,
        ?string $approvedDate,
        string $updated,
    ): array {
        return [
            'id' => $id,
            'requester_id' => $by,
            'request_date' => $sent,
            'trip_date' => SeedDates::date($day),
            'trip_end_date' => SeedDates::date($day),
            'trip_type' => 'inclusive',
            'departure_time' => '08:00:00',
            'destination' => $destination,
            'purpose' => $purpose,
            'estimated_return_time' => '16:00:00',
            'passengers' => $this->passengers($by, $count),
            'number_of_passengers' => $count,
            'status' => $status,
            'disapproval_reason' => $reason,
            'edit_reason' => null,
            'approved_by' => $approver,
            'approved_date' => $approvedDate,
            'created_at' => $sent,
            'updated_at' => $updated,
        ];
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