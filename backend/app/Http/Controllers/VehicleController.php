<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;

/**
 * NOTE: You already have a VehicleController from earlier sprints.
 * This file just shows the new `status()` action — copy that method
 * into your existing controller rather than replacing the whole file.
 */
class VehicleController extends Controller
{
    /**
     * GET /api/vehicles/{id}/status
     *
     * The "is this vehicle ready to dispatch" check Sprint 4 depends on.
     */
    public function status(Vehicle $vehicle)
    {
        $latest = $vehicle->latestMaintenanceLog;

        return response()->json([
            'id' => $vehicle->id,
            'plate_number' => $vehicle->plate_number,
            'status' => $vehicle->status,
            'last_maintenance' => $latest ? [
                'type' => $latest->maintenance_type,
                'date_performed' => $latest->maintenance_date?->format('Y-m-d'),
            ] : null,
            'next_due_date' => $latest?->next_due_date?->format('Y-m-d'),
        ]);
    }
}
