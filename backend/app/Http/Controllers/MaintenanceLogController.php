<?php

namespace App\Http\Controllers;

use App\Models\VehicleMaintenance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceLogController extends Controller
{
    private const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    private const VEHICLE_COLUMNS = 'vehicle:id,plate_number,vehicle_model,mileage';

    /**
     * GET /api/maintenance-logs
     * Query params: ?vehicle_id=3, ?type=repair
     *
     * Note: the query param and JSON response use the API's naming
     * (`type`, `date_performed`), but under the hood these map to the
     * database's actual columns (`maintenance_type`, `maintenance_date`).
     */
    public function index(Request $request)
    {
        $query = VehicleMaintenance::query()->with(self::VEHICLE_COLUMNS);

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->query('vehicle_id'));
        }

        if ($request->filled('type')) {
            $query->where('maintenance_type', $request->query('type'));
        }

        $logs = $query->orderByDesc('maintenance_date')->get();

        return response()->json([
            'data' => $logs->map(fn (VehicleMaintenance $log) => $this->format($log)),
        ]);
    }

    /**
     * POST /api/maintenance-logs
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'type' => ['required', Rule::in(VehicleMaintenance::TYPES)],
            'description' => ['nullable', 'string', 'max:500'],
            'date_performed' => ['required', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:date_performed'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
        ]);

        $log = VehicleMaintenance::create([
            'vehicle_id' => $validated['vehicle_id'],
            'maintenance_type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'maintenance_date' => $validated['date_performed'],
            'cost' => $validated['cost'] ?? null,
            'next_due_date' => $validated['next_due_date'] ?? null,
            'status' => $validated['status'] ?? 'scheduled',
        ]);
        $log->load(self::VEHICLE_COLUMNS);

        // Keep the vehicle's own maintenance-date fields in sync.
        $log->vehicle->update([
            'last_maintenance_date' => $log->maintenance_date,
            'next_maintenance_date' => $log->next_due_date,
        ]);

        return response()->json($this->format($log->fresh(self::VEHICLE_COLUMNS)), 201);
    }

    /**
     * PATCH /api/maintenance-logs/{id}
     */
    public function update(Request $request, VehicleMaintenance $maintenanceLog)
    {
        $validated = $request->validate([
            'vehicle_id' => ['sometimes', 'integer', 'exists:vehicles,id'],
            'type' => ['sometimes', Rule::in(VehicleMaintenance::TYPES)],
            'description' => ['nullable', 'string', 'max:500'],
            'date_performed' => ['sometimes', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:date_performed'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
        ]);

        $mapped = [];
        if (array_key_exists('vehicle_id', $validated)) {
            $mapped['vehicle_id'] = $validated['vehicle_id'];
        }
        if (array_key_exists('type', $validated)) {
            $mapped['maintenance_type'] = $validated['type'];
        }
        if (array_key_exists('description', $validated)) {
            $mapped['description'] = $validated['description'];
        }
        if (array_key_exists('date_performed', $validated)) {
            $mapped['maintenance_date'] = $validated['date_performed'];
        }
        if (array_key_exists('cost', $validated)) {
            $mapped['cost'] = $validated['cost'];
        }
        if (array_key_exists('next_due_date', $validated)) {
            $mapped['next_due_date'] = $validated['next_due_date'];
        }
        if (array_key_exists('status', $validated)) {
            $mapped['status'] = $validated['status'];
        }

        $maintenanceLog->update($mapped);
        $maintenanceLog->load(self::VEHICLE_COLUMNS);

        // Re-sync the vehicle's cached dates if this is still its latest log.
        $latest = $maintenanceLog->vehicle->latestMaintenanceLog;
        if ($latest && $latest->is($maintenanceLog)) {
            $maintenanceLog->vehicle->update([
                'last_maintenance_date' => $maintenanceLog->maintenance_date,
                'next_maintenance_date' => $maintenanceLog->next_due_date,
            ]);
        }

        return response()->json($this->format($maintenanceLog));
    }

    private function format(VehicleMaintenance $log): array
    {
        return [
            'id' => $log->id,
            'vehicle' => [
                'id' => $log->vehicle->id,
                'plate_number' => $log->vehicle->plate_number,
                'model' => $log->vehicle->vehicle_model,
                'mileage' => $log->vehicle->mileage,
            ],
            'type' => $log->maintenance_type,
            'description' => $log->description,
            'status' => $log->status,
            'date_performed' => $log->maintenance_date?->format('Y-m-d'),
            'cost' => $log->cost !== null ? (float) $log->cost : null,
            'next_due_date' => $log->next_due_date?->format('Y-m-d'),
            'created_at' => $log->created_at?->toISOString(),
        ];
    }
}