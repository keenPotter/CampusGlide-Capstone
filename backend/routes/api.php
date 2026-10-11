<?php

use App\Http\Controllers\Api\AllocationController;
use App\Http\Controllers\Api\AllocationQuickAddController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RescheduleRequestController;
use App\Http\Controllers\Api\TripRequestShareController;
use App\Http\Controllers\Api\VehicleRequestController as ApiVehicleRequestController;

use App\Http\Controllers\VehicleRequestPrintController;
use App\Http\Controllers\FuelUsageRecordController;
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\PostTravelReportController;
use App\Http\Controllers\PreventiveMaintenanceChecklistController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;

use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleRequest;

use Illuminate\Support\Facades\Route;

// ---------- Public ----------
Route::post('/login', [AuthController::class, 'login']);

// ---------- Maintenance Monitoring ----------
Route::get('/maintenance-logs', [MaintenanceLogController::class, 'index']);
Route::post('/maintenance-logs', [MaintenanceLogController::class, 'store']);
Route::patch('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'update']);
Route::get('/vehicles/{vehicle}/status', [VehicleController::class, 'status']);

Route::get('/vehicles', function () {
    return Vehicle::query()->orderBy('plate_number')->get();
});

Route::get('/post-travel-reports', [PostTravelReportController::class, 'index']);
Route::post('/post-travel-reports', [PostTravelReportController::class, 'store']);
Route::patch('/post-travel-reports/{postTravelReport}', [PostTravelReportController::class, 'update']);

Route::get('/fuel-usage-records', [FuelUsageRecordController::class, 'index']);
Route::post('/fuel-usage-records', [FuelUsageRecordController::class, 'store']);
Route::patch('/fuel-usage-records/{fuelUsageRecord}', [FuelUsageRecordController::class, 'update']);

Route::get('/preventive-maintenance-checklists', [PreventiveMaintenanceChecklistController::class, 'index']);
Route::post('/preventive-maintenance-checklists', [PreventiveMaintenanceChecklistController::class, 'store']);
Route::patch('/preventive-maintenance-checklists/{preventiveMaintenanceChecklist}', [PreventiveMaintenanceChecklistController::class, 'update']);

// ---------- Trip Scheduling ----------
Route::get('/vehicle-requests/approved', function () {
    return VehicleRequest::with('requester')
        ->where('status', 'approved')
        ->whereDoesntHave('trip')
        ->orderBy('trip_date')
        ->orderBy('departure_time')
        ->get()
        ->map(function ($request) {
            return [
                'id' => $request->id,
                'requester_id' => $request->requester_id,
                'requester_name' => $request->requester
                    ? trim($request->requester->first_name . ' ' . $request->requester->last_name)
                    : '—',
                'trip_date' => $request->trip_date?->format('Y-m-d'),
                'departure_time' => $request->departure_time,
                'estimated_return_time' => $request->estimated_return_time,
                'destination' => $request->destination,
                'purpose' => $request->purpose,
                'number_of_passengers' => $request->number_of_passengers,
                'status' => $request->status,
            ];
        });
});

Route::get('/users', [UserController::class, 'index']);
Route::get('/trips', [TripController::class, 'index']);
Route::post('/trips', [TripController::class, 'store']);
Route::get('/trips/{id}', [TripController::class, 'show']);
Route::put('/trips/{id}', [TripController::class, 'update']);
Route::delete('/trips/{id}', [TripController::class, 'destroy']);

Route::get('/drivers', function () {
    return Driver::orderBy('last_name')->orderBy('first_name')->get();
});

// ---------- Authenticated ----------
Route::middleware('auth:sanctum')->group(function () {
    // Existing authentication routes from develop
    Route::get('/user', [\App\Http\Controllers\AuthController::class, 'user']);
    Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);

    // Vehicle requests
    Route::get('/vehicle-requests', [ApiVehicleRequestController::class, 'index']);

    // Keep faculty's scheduled trips private to the logged-in faculty member.
    Route::get('/my-trips', [TripController::class, 'myTrips'])
        ->middleware('role:faculty');

    // Trip request sharing
    Route::get('/admins', [TripRequestShareController::class, 'admins']);
    Route::get('/trip-request-shares', [TripRequestShareController::class, 'index']);
    Route::post('/trip-request-shares', [TripRequestShareController::class, 'store']);

    // Allocation quick-add
    Route::post('/allocation-drivers', [AllocationQuickAddController::class, 'storeDriver']);
    Route::post('/allocation-vehicles', [AllocationQuickAddController::class, 'storeVehicle']);

    // Reschedule requests
    Route::get('/reschedule-requests', [RescheduleRequestController::class, 'index']);
    Route::post('/allocations/{allocation}/reschedule-requests', [RescheduleRequestController::class, 'store']);
    Route::post('/allocations/{allocation}/reschedule', [RescheduleRequestController::class, 'reschedule']);
    Route::post('/reschedule-requests/{rescheduleRequest}/approve', [RescheduleRequestController::class, 'approve']);
    Route::post('/reschedule-requests/{rescheduleRequest}/cancel', [RescheduleRequestController::class, 'cancel']);

    // Allocations
    Route::get('/allocation-options', [AllocationController::class, 'options']);
    Route::get('/allocations/{allocation}/print', [AllocationController::class, 'print']);
    Route::apiResource('allocations', AllocationController::class)
        ->only(['index', 'show', 'store', 'update']);

    // Vehicle-request operations requiring authentication
    Route::get('/vehicle-requests/{vehicleRequest}', [
        \App\Http\Controllers\VehicleRequestController::class,
        'show',
    ])->whereNumber('vehicleRequest');

    Route::middleware('role:faculty')->group(function () {
    Route::post('/vehicle-requests', [ApiVehicleRequestController::class, 'store']);

    Route::put('/vehicle-requests/{vehicleRequest}/edit', [
        \App\Http\Controllers\VehicleRequestController::class,
        'edit',
        ]);
    });

    Route::middleware('role:administrator')->group(function () {
    Route::patch('/vehicle-requests/{vehicleRequest}/status', [
        \App\Http\Controllers\VehicleRequestController::class,
        'updateStatus',
    ]);

    Route::get('/vehicle-requests/{vehicleRequest}/print', [
        VehicleRequestPrintController::class,
        'show',
    ])->whereNumber('vehicleRequest');

    Route::post('/register', [
        \App\Http\Controllers\AuthController::class,
        'register',
        ]);
    });
});