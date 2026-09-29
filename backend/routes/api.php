<?php

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