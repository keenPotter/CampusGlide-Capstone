<?php

namespace App\Http\Controllers;

use App\Models\RescheduleRequest;
use App\Models\VehicleMaintenance;
use App\Models\VehicleRequest;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/notifications/summary
 * The four numbers shown on the sidebar. Administrators only (route middleware).
 *
 * This is one small call that only counts rows. Before, the frontend loaded four full lists
 * (requests, maintenance, approved requests, date changes) on every page change just to count them.
 */
class NotificationSummaryController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => [
                // Vehicle requests waiting for a decision.
                'pending_requests' => VehicleRequest::where('status', 'pending')->count(),
                // Maintenance records that are in progress.
                'open_maintenance' => VehicleMaintenance::where('status', 'in_progress')->count(),
                // Approved requests that still need a vehicle and driver.
                'needs_allocation' => VehicleRequest::where('status', 'approved')->whereDoesntHave('trip')->count(),
                // Faculty requests to change the date of a trip.
                'pending_date_changes' => RescheduleRequest::where('status', 'pending')->count(),
            ],
        ]);
    }
}