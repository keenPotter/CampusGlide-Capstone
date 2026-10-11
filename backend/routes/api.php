<?php

use App\Http\Controllers\Api\AllocationController;
use App\Http\Controllers\Api\AllocationQuickAddController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RescheduleRequestController;
use App\Http\Controllers\Api\TripRequestShareController;
use App\Http\Controllers\Api\VehicleRequestController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('vehicle-requests', [VehicleRequestController::class, 'index']);
    Route::post('vehicle-requests', [VehicleRequestController::class, 'store']);
    Route::post('vehicle-requests/{vehicleRequest}/approve', [VehicleRequestController::class, 'approve']);
    Route::patch('vehicle-requests/{vehicleRequest}/status', [VehicleRequestController::class, 'updateStatus']);

    Route::get('admins', [TripRequestShareController::class, 'admins']);
    Route::get('trip-request-shares', [TripRequestShareController::class, 'index']);
    Route::post('trip-request-shares', [TripRequestShareController::class, 'store']);

    Route::post('allocation-drivers', [AllocationQuickAddController::class, 'storeDriver']);
    Route::post('allocation-vehicles', [AllocationQuickAddController::class, 'storeVehicle']);

    Route::get('reschedule-requests', [RescheduleRequestController::class, 'index']);
    Route::post('allocations/{allocation}/reschedule-requests', [RescheduleRequestController::class, 'store']);
    Route::post('allocations/{allocation}/reschedule', [RescheduleRequestController::class, 'reschedule']);
    Route::post('reschedule-requests/{rescheduleRequest}/approve', [RescheduleRequestController::class, 'approve']);
    Route::post('reschedule-requests/{rescheduleRequest}/cancel', [RescheduleRequestController::class, 'cancel']);
    Route::get('allocation-options', [AllocationController::class, 'options']);
    Route::get('allocations/{allocation}/print', [AllocationController::class, 'print']);

    Route::apiResource('allocations', AllocationController::class)
        ->only(['index', 'show', 'store', 'update']);
});
