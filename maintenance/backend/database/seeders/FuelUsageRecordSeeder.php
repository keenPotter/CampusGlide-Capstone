<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * end_trip_balance = balance_in_tank + issuance_from_stock + fuel_purchased - fuel_used
 */
class FuelUsageRecordSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            'id', 'vehicle_id', 'trip_id', 'record_date', 'balance_in_tank', 'issuance_from_stock',
            'fuel_purchased', 'fuel_used', 'end_trip_balance', 'riv_no', 'riv_date', 'or_no', 'or_date',
            'lubricating_oil', 'diesel_water', 'gear_oil', 'brake_fluid', 'flushing_oil', 'grease',
            'drivers', 'created_at', 'updated_at',
        ];

        $records = [
            [1, 2, 1,    '2026-08-28', 15.00, 30.00, 10.00, 41.00, 14.00, 'RIV-2026-0142', '2026-08-27', 'OR-558921', '2026-08-28', 1.00, null, null, null, null, null, 'Pedro Bautista',     '2026-08-28 19:00:00', '2026-08-28 19:00:00'],
            [2, 1, 2,    '2026-09-03', 25.00, 40.00, 20.00, 70.00, 15.00, 'RIV-2026-0155', '2026-09-02', 'OR-559310', '2026-09-03', null, null, null, null, null, null, 'Juan Reyes',         '2026-09-04 17:15:00', '2026-09-04 17:15:00'],
            [3, 5, 3,    '2026-09-10', 18.00, 0.00,  0.00,  3.50,  14.50, null,            null,         null,        null,         null, null, null, null, null, null, 'Carlos Mendoza',     '2026-09-10 14:40:00', '2026-09-10 14:40:00'],
            [4, 3, 4,    '2026-09-15', 12.00, 30.00, 0.00,  34.00, 8.00,  'RIV-2026-0161', '2026-09-14', null,        null,         null, null, null, null, null, 0.50, 'Carlos Mendoza',     '2026-09-15 18:10:00', '2026-09-15 18:10:00'],
            [5, 8, 5,    '2026-09-18', 40.00, 30.00, 25.00, 80.00, 15.00, 'RIV-2026-0170', '2026-09-17', 'OR-560144', '2026-09-18', null, null, null, null, null, null, 'Ernesto Villanueva', '2026-09-19 18:45:00', '2026-09-19 18:45:00'],
            [6, 1, 6,    '2026-09-24', 15.00, 10.00, 0.00,  12.00, 13.00, 'RIV-2026-0178', '2026-09-23', null,        null,         null, null, null, null, null, null, 'Juan Reyes',         '2026-09-24 16:40:00', '2026-09-24 16:40:00'],
            [7, 6, null, '2026-09-02', 10.00, 0.00,  40.00, 6.00,  44.00, null,            null,         'OR-559702', '2026-09-02', null, null, null, null, null, null, 'Ernesto Villanueva', '2026-09-02 16:00:00', '2026-09-02 16:00:00'],
        ];

        DB::table('fuel_usage_records')->insert(
            array_map(fn (array $row) => array_combine($columns, $row), $records)
        );
    }
}