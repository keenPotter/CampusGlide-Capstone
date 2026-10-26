<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Drivers are standalone records (not users). Admins pick them when scheduling trips.
 * - Drivers 1-4 and 6 have trips, so they are marked unavailable (the same way the allocation module does it).
 * - Driver 3's license expires in 20 days. Trips after that date show "License expires before the trip date".
 * - Driver 5 was marked unavailable by hand (on leave) and has no trip. This shows "Marked unavailable".
 */
class DriverSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'first_name', 'last_name', 'license_number', 'license_expiry_date', 'contact_number',
            'address', 'assigned_vehicle_id', 'is_available', 'created_at', 'updated_at',
        ];

        $created = SeedDates::at(-55, '08:00:00');
        $touched = SeedDates::at(-1, '07:10:00');

        $drivers = [
            [1, 'Juan',     'Reyes',      'N02-19-012345', SeedDates::date(565),  '09181234503', 'Brgy. Bonfal West, Bayombong, Nueva Vizcaya',        8,    false, $created, $touched],
            [2, 'Pedro',    'Bautista',   'N02-17-067890', SeedDates::date(400),  '09181234504', 'Brgy. Magsaysay, Bayombong, Nueva Vizcaya',          2,    false, $created, $touched],
            [3, 'Carlos',   'Mendoza',    'N02-15-034567', SeedDates::date(20),   '09181234505', 'Brgy. Don Mariano Marcos, Bayombong, Nueva Vizcaya', 1,    false, $created, $touched],
            [4, 'Ernesto',  'Villanueva', 'N02-20-045678', SeedDates::date(850),  '09181234506', 'Brgy. Ipil-Cuneg, Bambang, Nueva Vizcaya',          6,    false, $created, $touched],
            [5, 'Rolando',  'Dizon',      'N02-18-078901', SeedDates::date(700),  '09181234507', 'Brgy. Busilac, Bayombong, Nueva Vizcaya',            null, false, $created, $created],
            [6, 'Armando',  'Cabrera',    'N02-21-056789', SeedDates::date(1100), '09181234508', 'Brgy. Bonfal East, Bayombong, Nueva Vizcaya',        8,    false, $created, $touched],
        ];

        DB::table('drivers')->insert(
            array_map(fn (array $row) => array_combine($columns, $row), $drivers)
        );
    }
}