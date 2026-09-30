<?php

namespace App\Http\Controllers;

use App\Models\PostTravelReport;
use Illuminate\Http\Request;

class PostTravelReportController extends Controller
{
    public function index(Request $request)
    {
        $query = PostTravelReport::with('vehicle:id,plate_number,vehicle_model');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        return response()->json([
            'data' => $query->orderByDesc('travel_date_from')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'travel_date_from' => ['required', 'date'],
            'travel_date_to' => ['nullable', 'date', 'after_or_equal:travel_date_from'],
            'places_of_travel' => ['required', 'string', 'max:500'],
            'defects_observed' => ['nullable', 'string'],
            'defects_incurred' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'drivers' => ['nullable', 'string', 'max:500'],
            'arrival_at' => ['nullable', 'date'],
        ]);

        $report = PostTravelReport::create($validated);
        $report->load('vehicle:id,plate_number,vehicle_model');

        return response()->json(['data' => $report], 201);
    }

    public function update(Request $request, PostTravelReport $postTravelReport)
    {
        $validated = $request->validate([
            'vehicle_id' => ['sometimes', 'integer', 'exists:vehicles,id'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'travel_date_from' => ['sometimes', 'date'],
            'travel_date_to' => ['nullable', 'date', 'after_or_equal:travel_date_from'],
            'places_of_travel' => ['sometimes', 'string', 'max:500'],
            'defects_observed' => ['nullable', 'string'],
            'defects_incurred' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'drivers' => ['nullable', 'string', 'max:500'],
            'arrival_at' => ['nullable', 'date'],
        ]);

        $postTravelReport->update($validated);
        $postTravelReport->load('vehicle:id,plate_number,vehicle_model');

        return response()->json(['data' => $postTravelReport]);
    }
}
