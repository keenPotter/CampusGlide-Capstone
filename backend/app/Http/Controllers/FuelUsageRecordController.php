<?php

namespace App\Http\Controllers;

use App\Models\FuelUsageRecord;
use Illuminate\Http\Request;

class FuelUsageRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = FuelUsageRecord::with('vehicle:id,plate_number,vehicle_model');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        if ($request->filled('trip_id')) {
            $query->where('trip_id', $request->integer('trip_id'));
        }

        return response()->json([
            'data' => $query->orderByDesc('record_date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $record = FuelUsageRecord::create($validated);
        $record->load('vehicle:id,plate_number,vehicle_model');

        return response()->json(['data' => $record], 201);
    }

    public function update(Request $request, FuelUsageRecord $fuelUsageRecord)
    {
        $fuelUsageRecord->update($this->validated($request, true));
        $fuelUsageRecord->load('vehicle:id,plate_number,vehicle_model');

        return response()->json(['data' => $fuelUsageRecord]);
    }
    private function validated(Request $request, bool $partial = false): array
    {
    return $request->validate([
        'vehicle_id' => [
            $partial ? 'sometimes' : 'required',
            'integer',
            'exists:vehicles,id'
        ],

        'trip_id' => [
            'nullable',
            'integer',
            'exists:trips,id'
        ],

        'record_date' => [
            $partial ? 'sometimes' : 'required',
            'date'
        ],

        'balance_in_tank' => [
            $partial ? 'sometimes' : 'required',
            'numeric',
            'min:0'
        ],

        'issuance_from_stock' => [
            $partial ? 'sometimes' : 'required',
            'numeric',
            'min:0'
        ],

        'fuel_purchased' => [
            $partial ? 'sometimes' : 'required',
            'numeric',
            'min:0'
        ],

        'fuel_used' => [
            $partial ? 'sometimes' : 'required',
            'numeric',
            'min:0'
        ],

        'end_trip_balance' => [
            $partial ? 'sometimes' : 'required',
            'numeric',
            'min:0'
        ],

        'riv_no' => ['nullable', 'string', 'max:100'],
        'riv_date' => ['nullable', 'date'],
        'or_no' => ['nullable', 'string', 'max:100'],
        'or_date' => ['nullable', 'date'],

        'lubricating_oil' => ['nullable', 'numeric', 'min:0'],
        'diesel_water' => ['nullable', 'numeric', 'min:0'],
        'gear_oil' => ['nullable', 'numeric', 'min:0'],
        'brake_fluid' => ['nullable', 'numeric', 'min:0'],
        'flushing_oil' => ['nullable', 'numeric', 'min:0'],
        'grease' => ['nullable', 'numeric', 'min:0'],

        'drivers' => ['nullable', 'string', 'max:500'],
    ]);
    }   
}
