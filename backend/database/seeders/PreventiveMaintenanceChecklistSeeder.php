<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PreventiveMaintenanceChecklistSeeder extends Seeder
{
    /**
     * Order matches the `ratings` arrays below.
     */
    private const RATING_COLUMNS = [
        'belts_condition', 'hoses_condition', 'engine_condition',
        'air_conditioning_condition', 'wipers_condition',
        'headlights_condition', 'driving_lights_condition',
        'brake_lights_condition', 'hazard_lights_condition',
        'door_locks_condition', 'windows_windshield_condition',
        'radio_condition', 'tires_condition', 'liquid_levels_condition',
        'other_parts_condition',
    ];

    public function run(): void
    {
        $rows = [
            $this->record(1, 1, -78, 'Rodel Quiambao', 84500, [0, 0, -180, 0, -180],
                ['good', 'good', 'excellent', 'good', 'good', 'excellent', 'good', 'excellent', 'excellent', 'good', 'good', 'good', 'good', 'excellent', 'good'],
                null, 'Vehicle in good overall condition.', 'Continue regular maintenance schedule.'),
            $this->record(2, 2, -103, 'Rodel Quiambao', 61200, [-61, -61, -181, -61, -181],
                ['good', 'good', 'good', 'excellent', 'poor', 'good', 'good', 'good', 'good', 'excellent', 'good', 'excellent', 'excellent', 'good', 'good'],
                null, 'Wipers worn out; tires newly replaced.', 'Replace wiper blades at next opportunity.'),
            $this->record(3, 3, -128, 'Rodel Quiambao', 102300, [-92, -182, null, -92, -182],
                ['good', 'poor', 'good', 'poor', 'good', 'good', 'good', 'good', 'good', 'good', 'good', 'poor', 'good', 'good', 'good'],
                'Radiator hose slightly cracked', 'Hoses and air conditioning need attention.', 'Replace radiator hose and schedule AC servicing.'),
            $this->record(4, 4, -31, 'Efren Dela Cruz', 156800, [-160, -160, -342, -160, -342],
                ['poor', 'good', 'poor', 'good', 'good', 'good', 'good', 'good', 'good', 'good', 'good', 'good', 'good', 'good', 'poor'],
                'Clutch assembly worn', 'Unit undergoing clutch replacement. Belts and engine need follow-up check.', 'Do not release unit until repairs are completed and re-inspected.'),
            $this->record(5, 5, -50, 'Rodel Quiambao', 38900, [0, 0, -184, 0, -184],
                ['excellent', 'excellent', 'excellent', 'excellent', 'good', 'excellent', 'excellent', 'excellent', 'excellent', 'excellent', 'excellent', 'good', 'excellent', 'excellent', 'excellent'],
                null, 'Excellent condition.', 'No action needed.'),
            $this->record(6, 7, -230, 'Efren Dela Cruz', 189000, [-128, -281, null, -128, -281],
                ['poor', 'poor', 'poor', 'poor', 'poor', 'poor', 'good', 'poor', 'good', 'poor', 'poor', 'poor', 'poor', 'poor', 'poor'],
                'Chassis corrosion', 'Vehicle beyond economical repair.', 'Recommend retirement of unit.'),
        ];

        DB::table('preventive_maintenance_checklists')->insert($rows);
    }

    /**
     * @param  int  $day  inspection day, counted from today
     * @param  array  $services  days before or after the inspection for: oil change, air filter,
     *                           cabin filter, oil filter, engine tune-up (null = never done)
     */
    private function record(
        int $id,
        int $vehicle,
        int $day,
        string $inspector,
        int $mileage,
        array $services,
        array $ratings,
        ?string $otherParts,
        string $remarks,
        string $recommendation,
    ): array {
        $date = fn (?int $shift) => $shift === null ? null : SeedDates::date($day + $shift);
        $filed = SeedDates::at($day, '15:30:00');

        return [
            'id' => $id,
            'vehicle_id' => $vehicle,
            'pmuv_no' => sprintf('PMUV-%s-%03d', substr(SeedDates::date($day), 0, 4), $id),
            'inspection_date' => SeedDates::date($day),
            'inspector_mechanic' => $inspector,
            'current_mileage' => $mileage,
            'last_oil_change' => $date($services[0]),
            'last_air_filter_change' => $date($services[1]),
            'last_cabin_filter_change' => $date($services[2]),
            'last_oil_filter_change' => $date($services[3]),
            'last_engine_tune_up' => $date($services[4]),
        ] + array_combine(self::RATING_COLUMNS, $ratings) + [
            'other_parts' => $otherParts,
            'remarks' => $remarks,
            'supervisor_recommendation' => $recommendation,
            'created_at' => $filed,
            'updated_at' => $filed,
        ];
    }
}