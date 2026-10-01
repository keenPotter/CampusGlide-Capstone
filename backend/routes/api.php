<?php
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
