<?php

use App\Http\Controllers\Api\AllocationController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| STAGING FILE — hindi ito auto-load ng Laravel.
| Kopyahin ang laman sa routes/api.php, tapos burahin ang file na ito.
| (Ang "/api" prefix ay awtomatiko — kaya /api/allocations ang final URL.)
|--------------------------------------------------------------------------
*/

// OPTIONAL: alisin kung may /login na ang team mo (na nagre-return ng token + role).
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Dapat NASA ITAAS ng apiResource at HINDI "allocations/options"
    Route::get('allocation-options', [AllocationController::class, 'options']);

    Route::apiResource('allocations', AllocationController::class)
        ->only(['index', 'show', 'store', 'update', 'destroy']);
});
