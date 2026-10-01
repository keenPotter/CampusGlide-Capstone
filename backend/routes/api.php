<?php
// ---------- Maintenance Monitoring ----------
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\PostTravelReportController;
use App\Http\Controllers\FuelUsageRecordController;
use App\Http\Controllers\PreventiveMaintenanceChecklistController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/maintenance-logs', [MaintenanceLogController::class, 'index']);
Route::post('/maintenance-logs', [MaintenanceLogController::class, 'store']);
Route::patch('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'update']);
Route::get('/vehicles/{vehicle}/status', [VehicleController::class, 'status']);
Route::get('/vehicles', function () {
    return response()->json(['data' => \App\Models\Vehicle::query()->select('id','plate_number','vehicle_model','vehicle_type','status')->orderBy('plate_number')->get()]);
});

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

// ---------- Vehicle Request ----------
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VehicleRequestController;
use App\Http\Controllers\Api\GuardLogController;
use Illuminate\Support\Facades\Route;

// ---------- Public ----------
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ---------- Authenticated ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Both roles can list/view; scoping happens inside the controller (administrators see all, faculties see their own logs)
    Route::get('/vehicle-requests', [VehicleRequestController::class, 'index']);
    Route::get('/vehicle-requests/{vehicleRequest}', [VehicleRequestController::class, 'show']);

    // Both roles can list/view; scoping happens inside the controller (administrators see all, guards see their own logs)
    Route::get('/guard-logs', [GuardLogController::class, 'index']);
    Route::get('/guard-logs/{guardLog}', [GuardLogController::class, 'show']);

    // Only faculty can create new requests and edit their own requests
    Route::middleware('role:faculty')->group(function () {
        Route::post('/vehicle-requests', [VehicleRequestController::class, 'store']);
        Route::put('/vehicle-requests/{vehicleRequest}/edit', [VehicleRequestController::class, 'edit']);
        Route::patch('/vehicle-requests/{vehicleRequest}/cancel', [VehicleRequestController::class, 'cancel']);
    });

    // Only administrators can update the status of requests
    Route::middleware('role:administrator')->group(function () {
        Route::patch('/vehicle-requests/{vehicleRequest}/status', [VehicleRequestController::class, 'updateStatus']);
    });

    // Only guards can record vehicle departures and returns
    Route::middleware('role:guard')->group(function () {
        Route::post('/guard-logs', [GuardLogController::class, 'store']);
        Route::patch('/guard-logs/{guardLog}/return', [GuardLogController::class, 'recordReturn']);
    });
});

// ---------- Trip Scheduling ----------
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserController;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\VehicleRequest;

Route::get('/users', [UserController::class, 'index']);

Route::get('/trips', [TripController::class, 'index']);

Route::post('/trips', [TripController::class, 'store']);

Route::get('/trips/{id}', [TripController::class, 'show']);

Route::put('/trips/{id}',[TripController::class, 'update']);

Route::delete('/trips/{id}', [TripController::class, 'destroy']);


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/vehicles', function () {
    return Vehicle::all();
});

Route::get('/drivers', function () {
    return Driver::with('user')->get();
});

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
                'trip_date' => $request->trip_date,
                'departure_time' => $request->departure_time,
                'estimated_return_time' => $request->estimated_return_time,
                'destination' => $request->destination,
                'purpose' => $request->purpose,
                'number_of_passengers' => $request->number_of_passengers,
                'status' => $request->status,
            ];
        });
});
