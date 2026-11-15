<?php

namespace App\Http\Controllers;

use App\Http\Resources\DriverResource;
use App\Models\Driver;

class DriverController extends Controller
{
    /**
     * GET /api/drivers
     * Administrators and staff can view drivers (route middleware).
     */
    public function index()
    {
        return DriverResource::collection(
            Driver::orderBy('last_name')->orderBy('first_name')->get()
        );
    }
}