<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripViewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'trip_ids' => TripView::query()
                ->where('user_id', $request->user()->id)
                ->pluck('trip_id'),
        ]);
    }

    public function store(Request $request, Trip $trip): JsonResponse
    {
        TripView::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'trip_id' => $trip->id,
            ],
            ['viewed_at' => now()],
        );

        return response()->json([
            'trip_id' => $trip->id,
            'viewed' => true,
        ]);
    }
}
