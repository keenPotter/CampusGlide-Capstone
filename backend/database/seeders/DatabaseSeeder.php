<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Run: php artisan migrate:fresh --seed
 * Optional bulk data for the ISO 25010 performance and capacity tests:
 *   php artisan db:seed --class=BulkRequestSeeder
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Tables in dependency order (parents first).
     */
    private const TABLES = [
        'personal_access_tokens',
        'users',
        'vehicles',
        'drivers',
        'vehicle_requests',
        'trips',
        'allocations',
        'reschedule_requests',
        'trip_request_shares',
        'vehicle_maintenance',
        'post_travel_reports',
        'fuel_usage_records',
        'preventive_maintenance_checklists',
    ];

    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (array_reverse(self::TABLES) as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        DB::transaction(function () {
            $this->call([
                UserSeeder::class,
                VehicleSeeder::class,
                DriverSeeder::class,
                VehicleRequestSeeder::class,
                TripSeeder::class,
                AllocationSeeder::class,
                RescheduleRequestSeeder::class,
                TripRequestShareSeeder::class,
                VehicleMaintenanceSeeder::class,
                PostTravelReportSeeder::class,
                FuelUsageRecordSeeder::class,
                PreventiveMaintenanceChecklistSeeder::class,
            ]);
        });
    }
}