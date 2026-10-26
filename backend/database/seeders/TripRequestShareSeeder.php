<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Trip requests sent from one administrator to the other,
 * so each administrator already has a "Received request" row to look at.
 */
class TripRequestShareSeeder extends Seeder
{
    public function run(): void
    {
        $allocationOf = fn (int $requestId) => DB::table('allocations')
            ->join('trips', 'trips.id', '=', 'allocations.trip_id')
            ->where('trips.vehicle_request_id', $requestId)
            ->value('allocations.id');

        $sent = SeedDates::at(-1, '11:00:00');

        DB::table('trip_request_shares')->insert([
            ['id' => 1, 'allocation_id' => $allocationOf(8), 'sender_id' => 1, 'recipient_id' => 2, 'created_at' => $sent, 'updated_at' => $sent],
            ['id' => 2, 'allocation_id' => $allocationOf(7), 'sender_id' => 2, 'recipient_id' => 1, 'created_at' => $sent, 'updated_at' => $sent],
        ]);
    }
}