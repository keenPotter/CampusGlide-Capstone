<?php

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

