<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceLogRequest;
use App\Http\Requests\UpdateMaintenanceLogRequest;
use App\Http\Resources\MaintenanceLogResource;
use App\Models\VehicleMaintenance;
use Illuminate\Http\Request;

class MaintenanceLogController extends Controller
{
    private const VEHICLE_COLUMNS = 'vehicle:id,plate_number,vehicle_model,mileage';

    /**
     * The API uses the names `type` and `date_performed`.
     * The database columns are `maintenance_type` and `maintenance_date`.
     */
    private const COLUMN_MAP = [
        'type' => 'maintenance_type',
        'date_performed' => 'maintenance_date',
    ];

    /**
     * GET /api/maintenance-logs
     * Query params: ?vehicle_id=3, ?type=repair, ?month=1, ?year=2026, ?page=1, ?per_page=10
     */
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:1900,2200'],
        ]);

        $query = VehicleMaintenance::query()->with(self::VEHICLE_COLUMNS);
        $today = now()->toDateString();

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->query('vehicle_id'));
        }

        if ($request->filled('type')) {
            $query->where('maintenance_type', $request->query('type'));
        }

        if ($request->filled('status')) {
            match ($request->query('status')) {
                'upcoming' => $query->where('status', 'scheduled')->where(function ($dateQuery) use ($today) {
                    $dateQuery->whereDate('maintenance_date', '>=', $today)->orWhereNull('maintenance_date');
                }),
                'overdue' => $query->where('status', 'scheduled')->whereDate('maintenance_date', '<', $today),
                default => $query->where('status', $request->query('status')),
            };
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('maintenance_type', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery->where('plate_number', 'like', "%{$search}%")
                            ->orWhere('vehicle_model', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('year')) {
            $query->whereYear('maintenance_date', $request->query('year'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('maintenance_date', $request->query('month'));
        }

        $summaryQuery = VehicleMaintenance::query();
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'this_month' => (clone $summaryQuery)->whereYear('maintenance_date', now()->year)
                ->whereMonth('maintenance_date', now()->month)->count(),
            'upcoming' => (clone $summaryQuery)->where('status', 'scheduled')->where(function ($dateQuery) use ($today) {
                $dateQuery->whereDate('maintenance_date', '>=', $today)->orWhereNull('maintenance_date');
            })->count(),
            'overdue' => (clone $summaryQuery)->where('status', 'scheduled')
                ->whereDate('maintenance_date', '<', $today)->count(),
        ];

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
        $page = $query
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 WHEN status = 'scheduled' AND maintenance_date >= ? THEN 1 WHEN status = 'scheduled' THEN 2 WHEN status = 'cancelled' THEN 3 WHEN status = 'completed' THEN 4 ELSE 5 END", [$today])
            ->orderByRaw("CASE WHEN status IN ('cancelled', 'completed') THEN maintenance_date END DESC")
            ->orderBy('maintenance_date')
            ->paginate($perPage)
            ->appends($request->query());

        return MaintenanceLogResource::collection($page)->additional(['summary' => $summary]);
    }

    /**
     * POST /api/maintenance-logs
     */
    public function store(StoreMaintenanceLogRequest $request)
    {
        $columns = array_merge(['status' => 'scheduled', 'maintenance_category' => 'preventive'], $this->toColumns($request->validated()));

        $log = VehicleMaintenance::create($columns);
        $log->load(self::VEHICLE_COLUMNS);

        $this->syncVehicleDates($log);

        return (new MaintenanceLogResource($log->fresh(self::VEHICLE_COLUMNS)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/maintenance-logs/{id}
     */
    public function update(UpdateMaintenanceLogRequest $request, VehicleMaintenance $maintenanceLog)
    {
        $maintenanceLog->update($this->toColumns($request->validated()));
        $maintenanceLog->load(self::VEHICLE_COLUMNS);

        $this->syncVehicleDates($maintenanceLog);

        return new MaintenanceLogResource($maintenanceLog);
    }

    /**
     * Rename API field names to database column names.
     */
    private function toColumns(array $validated): array
    {
        $columns = [];

        foreach ($validated as $key => $value) {
            $columns[self::COLUMN_MAP[$key] ?? $key] = $value;
        }

        return $columns;
    }

    /**
     * Keep the vehicle's own maintenance dates in sync,
     * but only when this log is the vehicle's latest one.
     */
    private function syncVehicleDates(VehicleMaintenance $log): void
    {
        $vehicle = $log->vehicle;

        if ($vehicle->latestMaintenanceLog?->is($log)) {
            $vehicle->update([
                'last_maintenance_date' => $log->maintenance_date->toDateString(),
                'next_maintenance_date' => $log->next_due_date?->toDateString(),
            ]);
        }
    }
}