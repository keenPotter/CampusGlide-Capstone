<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VehicleRequestController;
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
// Login is the only route that can be used without a token.
// The throttle allows 10 attempts per minute to slow down password guessing.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// ---------- Authenticated (every route below needs a valid token) ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Everyone who is logged in can list/view; scoping happens inside the controller
    Route::get('/vehicle-requests', [VehicleRequestController::class, 'index']);
    Route::get('/vehicle-requests/{vehicleRequest}', [VehicleRequestController::class, 'show'])
        ->whereNumber('vehicleRequest');

    // ---------- Faculty only ----------
    Route::middleware('role:faculty')->group(function () {
        Route::post('/vehicle-requests', [VehicleRequestController::class, 'store']);
        Route::put('/vehicle-requests/{vehicleRequest}/edit', [VehicleRequestController::class, 'edit']);
        Route::get('/my-trips', [TripController::class, 'myTrips']);
    });

    // ---------- Administrator only ----------
    Route::middleware('role:administrator')->group(function () {
        // Accounts
        Route::post('/register', [AuthController::class, 'register']);
        Route::get('/users', [UserController::class, 'index']);

        // Vehicle request approval and printing
        Route::patch('/vehicle-requests/{vehicleRequest}/status', [VehicleRequestController::class, 'updateStatus']);
        Route::get('/vehicle-requests/{vehicleRequest}/print', [VehicleRequestPrintController::class, 'show'])
            ->whereNumber('vehicleRequest');

        // Approved requests waiting to be scheduled ('whereNumber' on the show route keeps "approved" from being read as an ID).
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
                        // Plain Y-m-d so it matches the dates on the trips list.
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

        // Trip scheduling
        Route::get('/trips', [TripController::class, 'index']);
        Route::post('/trips', [TripController::class, 'store']);
        Route::get('/trips/{id}', [TripController::class, 'show']);
        Route::put('/trips/{id}', [TripController::class, 'update']);
        Route::delete('/trips/{id}', [TripController::class, 'destroy']);

        // Driver list for the dropdown when scheduling a trip.
        Route::get('/drivers', function () {
            return Driver::orderBy('last_name')->orderBy('first_name')->get();
        });

        // Vehicles (the trip schedule and maintenance pages both read this).
        Route::get('/vehicles', function () {
            return Vehicle::query()->orderBy('plate_number')->get();
        });
        Route::get('/vehicles/{vehicle}/status', [VehicleController::class, 'status']);

        // Maintenance Monitoring
        Route::get('/maintenance-logs', [MaintenanceLogController::class, 'index']);
        Route::post('/maintenance-logs', [MaintenanceLogController::class, 'store']);
        Route::patch('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'update']);

        // Fleet forms captured from the NVSU Motor Pool documents.
        Route::get('/post-travel-reports', [PostTravelReportController::class, 'index']);
        Route::post('/post-travel-reports', [PostTravelReportController::class, 'store']);
        Route::patch('/post-travel-reports/{postTravelReport}', [PostTravelReportController::class, 'update']);

        Route::get('/fuel-usage-records', [FuelUsageRecordController::class, 'index']);
        Route::post('/fuel-usage-records', [FuelUsageRecordController::class, 'store']);
        Route::patch('/fuel-usage-records/{fuelUsageRecord}', [FuelUsageRecordController::class, 'update']);

        Route::get('/preventive-maintenance-checklists', [PreventiveMaintenanceChecklistController::class, 'index']);
        Route::post('/preventive-maintenance-checklists', [PreventiveMaintenanceChecklistController::class, 'store']);
        Route::patch('/preventive-maintenance-checklists/{preventiveMaintenanceChecklist}', [PreventiveMaintenanceChecklistController::class, 'update']);
    });
});