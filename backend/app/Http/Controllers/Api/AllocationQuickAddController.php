<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\AllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * "Add new driver" / "Add new vehicle" mula mismo sa allocation page. Admin lang.
 * Columns na wala sa table ay awtomatikong nilalaktawan (iba-iba ang schema ng Driver/Vehicle Management).
 */
class AllocationQuickAddController extends Controller
{
    public function storeDriver(Request $request): JsonResponse
    {
        abort_unless(AllocationService::isAdmin($request->user()), 403, 'Admin only.');

        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact_number' => ['required', 'string', 'max:30'],
            'license_number' => ['required', 'string', 'max:50'],
            'license_expiry_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $data = [
            'contact_number' => $v['contact_number'],
            'license_number' => $v['license_number'],
            'license_expiry_date' => $v['license_expiry_date'],
            'is_available' => true,
        ];

        if (Schema::hasColumn('drivers', 'name')) {
            $data['name'] = $v['name'];
        } else {
            $parts = preg_split('/\s+/', trim($v['name']));
            $data['last_name'] = count($parts) > 1 ? array_pop($parts) : $parts[0];
            $data['first_name'] = count($parts) ? implode(' ', $parts) : $data['last_name'];
        }

        $driver = Driver::forceCreate($this->onlyExistingColumns('drivers', $data));

        return response()->json(['data' => AllocationService::driverOption($driver)], 201);
    }

    public function storeVehicle(Request $request): JsonResponse
    {
        abort_unless(AllocationService::isAdmin($request->user()), 403, 'Admin only.');

        $v = $request->validate([
            'plate_number' => ['required', 'string', 'max:20', 'unique:vehicles,plate_number'],
            'vehicle_model' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $data = $v + ['status' => 'available', 'is_active' => true];

        $vehicle = Vehicle::forceCreate($this->onlyExistingColumns('vehicles', $data));

        return response()->json(['data' => AllocationService::vehicleOption($vehicle)], 201);
    }

    private function onlyExistingColumns(string $table, array $data): array
    {
        $columns = Schema::getColumnListing($table);

        return array_filter($data, fn ($v, $k) => in_array($k, $columns, true) && $v !== null, ARRAY_FILTER_USE_BOTH);
    }
}
