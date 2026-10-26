<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A vehicle with a scheduled or in-progress trip is marked "in_use", the same way the
 * allocation module does it. Vehicle 4 is under repair and vehicle 7 is retired.
 */
class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'plate_number', 'vehicle_model', 'vehicle_type', 'color', 'manufacture_year',
            'capacity', 'mileage', 'status', 'last_maintenance_date', 'next_maintenance_date',
            'is_active', 'created_at', 'updated_at',
        ];

        $created = SeedDates::at(-55, '08:00:00');
        $touched = SeedDates::at(-1, '07:10:00');
        $d = fn (?int $n) => $n === null ? null : SeedDates::date($n);

        $vehicles = [
            [1, 'SAA 1234', 'Toyota Hiace Commuter', 'Van',         'White',       2019, 15, 84500,  'in_use',      $d(-78),  $d(105), true,  $created, $touched],
            [2, 'NBC 5521', 'Toyota Innova',         'MPV',         'Silver',      2020, 7,  61200,  'in_use',      $d(-103), $d(262), true,  $created, $touched],
            // Next maintenance date already passed (shows as overdue).
            [3, 'NAB 7712', 'Mitsubishi L300 FB',    'Utility Van', 'White',       2018, 3,  102300, 'in_use',      $d(-128), $d(-6),  true,  $created, $touched],
            [4, 'NCD 3345', 'Toyota Coaster',        'Bus',         'White',       2017, 30, 156800, 'maintenance', $d(-191), $d(10),  true,  $created, SeedDates::at(-31, '09:00:00')],
            [5, 'AEF 6098', 'Toyota Vios',           'Sedan',       'Black',       2021, 4,  38900,  'in_use',      $d(-50),  $d(130), true,  $created, $touched],
            [6, 'NGH 2271', 'Isuzu D-Max',           'Pickup',      'Gray',        2022, 5,  27400,  'in_use',      $d(-62),  $d(120), true,  $created, $touched],
            [7, 'ABK 4410', 'Nissan Urvan NV350',    'Van',         'Silver',      2016, 14, 189000, 'retired',     $d(-345), null,    false, $created, $created],
            [8, 'NLM 8830', 'Toyota Fortuner',       'SUV',         'Pearl White', 2023, 7,  15600,  'in_use',      $d(-43),  $d(139), true,  $created, $touched],
        ];

        DB::table('vehicles')->insert(
            array_map(fn (array $row) => array_combine($columns, $row), $vehicles)
        );
    }
}