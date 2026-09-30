<?php

namespace App\Http\Controllers;

use App\Models\PreventiveMaintenanceChecklist;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreventiveMaintenanceChecklistController extends Controller
{
    private const RATING_FIELDS = [
        'belts_condition', 'hoses_condition', 'engine_condition',
        'air_conditioning_condition', 'wipers_condition',
        'headlights_condition', 'driving_lights_condition',
        'brake_lights_condition', 'hazard_lights_condition',
        'door_locks_condition', 'windows_windshield_condition',
        'radio_condition', 'tires_condition', 'liquid_levels_condition',
        'other_parts_condition',
    ];

    public function index(Request $request)
    {
        $query = PreventiveMaintenanceChecklist::with('vehicle:id,plate_number,vehicle_model');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        return response()->json([
            'data' => $query->orderByDesc('inspection_date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $checklist = PreventiveMaintenanceChecklist::create($this->validated($request));
        $checklist->load('vehicle:id,plate_number,vehicle_model');

        return response()->json(['data' => $checklist], 201);
    }

    public function update(Request $request, PreventiveMaintenanceChecklist $preventiveMaintenanceChecklist)
    {
        $preventiveMaintenanceChecklist->update($this->validated($request, true));
        $preventiveMaintenanceChecklist->load('vehicle:id,plate_number,vehicle_model');

        return response()->json(['data' => $preventiveMaintenanceChecklist]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'vehicle_id' => [$partial ? 'sometimes' : 'required', 'integer', 'exists:vehicles,id'],
            'pmuv_no' => ['nullable', 'string', 'max:100'],
            'inspection_date' => [$partial ? 'sometimes' : 'required', 'date'],
            'inspector_mechanic' => ['nullable', 'string', 'max:255'],
            'current_mileage' => ['nullable', 'integer', 'min:0'],
            'last_oil_change' => ['nullable', 'date'],
            'last_air_filter_change' => ['nullable', 'date'],
            'last_cabin_filter_change' => ['nullable', 'date'],
            'last_oil_filter_change' => ['nullable', 'date'],
            'last_engine_tune_up' => ['nullable', 'date'],
            'other_parts' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'supervisor_recommendation' => ['nullable', 'string'],
        ];

        foreach (self::RATING_FIELDS as $field) {
            $rules[$field] = ['nullable', Rule::in(PreventiveMaintenanceChecklist::RATINGS)];
        }

        return $request->validate($rules);
    }
}
