<?php

use App\Http\Controllers\AllocationController;
use App\Http\Controllers\AllocationPrintController;
use App\Http\Controllers\AllocationQuickAddController;
use App\Http\Controllers\AdministratorTransferController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\FuelUsageRecordController;
use App\Http\Controllers\MaintenanceLogController;
use App\Http\Controllers\NotificationSummaryController;
use App\Http\Controllers\PostTravelReportController;
use App\Http\Controllers\PreventiveMaintenanceChecklistController;
use App\Http\Controllers\PreventiveMaintenanceChecklistPrintController;
use App\Http\Controllers\RescheduleRequestController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripRequestShareController;
use App\Http\Controllers\TripViewController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleRequestController;
use Illuminate\Support\Facades\Route;

// ---------- Public ----------
// Login is the only route that can be used without a token.
// Login attempts are limited per email/IP pair and per IP.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// ---------- Authenticated (every route below needs a valid token) ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('audit:LOGOUT,users');

    // Everyone who is logged in can list/view; scoping happens inside the controller
    Route::get('/vehicle-requests', [VehicleRequestController::class, 'index']);
    Route::get('/vehicle-requests/{vehicleRequest}', [VehicleRequestController::class, 'show'])
        ->whereNumber('vehicleRequest');

    // Allocations and date change requests: administrators and staff see all, faculty see only their own
    Route::get('/allocations', [AllocationController::class, 'index']);
    Route::get('/allocations/{allocation}', [AllocationController::class, 'show'])
        ->whereNumber('allocation');
    Route::get('/reschedule-requests', [RescheduleRequestController::class, 'index']);

    // ---------- Fleet views for administrators and staff ----------
    // Staff may view the trip schedule and its related fleet details.
    Route::middleware('role:administrator,staff')->group(function () {
        Route::get('/trips', [TripController::class, 'index']);
        Route::get('/trips/{trip}', [TripController::class, 'show'])->whereNumber('trip');
        Route::get('/trip-views', [TripViewController::class, 'index']);
        Route::post('/trips/{trip}/view', [TripViewController::class, 'store'])
            ->whereNumber('trip')
            ->middleware('audit:VIEW_TRIP_DETAILS,trips');
        Route::get('/drivers', [DriverController::class, 'index']);
        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::get('/vehicles/{vehicle}/status', [VehicleController::class, 'status']);
    });

    // ---------- Allocation views and ticket sharing for administrators and staff ----------
    // Mutations are administrator-only below; staff retain read-only views and ticket sharing.
    Route::middleware('role:administrator,staff')->group(function () {
        Route::get('/allocation-options', [AllocationController::class, 'options']);
        Route::get('/allocations/{allocation}/print', [AllocationPrintController::class, 'show'])
            ->whereNumber('allocation');

        // Sending a trip request to an administrator
        Route::get('/admins', [TripRequestShareController::class, 'admins']);
        Route::get('/trip-request-shares', [TripRequestShareController::class, 'index']);
        Route::post('/trip-request-shares', [TripRequestShareController::class, 'store'])
            ->middleware('audit:SEND_TRIP_REQUEST,trip_request_shares');
    });

    // ---------- Faculty only ----------
    Route::middleware('role:faculty')->group(function () {
        Route::post('/vehicle-requests', [VehicleRequestController::class, 'store'])
            ->middleware('audit:CREATE_VEHICLE_REQUEST,vehicle_requests');
        Route::put('/vehicle-requests/{vehicleRequest}/edit', [VehicleRequestController::class, 'edit'])
            ->middleware('audit:EDIT_VEHICLE_REQUEST,vehicle_requests');
        Route::get('/my-trips', [TripController::class, 'myTrips']);

        // Faculty ask for a date change; the administrator decides.
        Route::post('/allocations/{allocation}/reschedule-requests', [RescheduleRequestController::class, 'store'])
            ->whereNumber('allocation')
            ->middleware('audit:CREATE_RESCHEDULE_REQUEST,reschedule_requests');
    });

    // ---------- Administrators and staff ----------
    Route::middleware('role:administrator,staff')->group(function () {
        // Sidebar numbers. One small call instead of four list calls on every page change.
        Route::get('/notifications/summary', [NotificationSummaryController::class, 'summary']);

        // Vehicle request approval
        Route::post('/vehicle-requests/on-site', [VehicleRequestController::class, 'storeOnSite'])
            ->middleware('audit:CREATE_ON_SITE_BOOKING,vehicle_requests');
        Route::patch('/vehicle-requests/{vehicleRequest}/status', [VehicleRequestController::class, 'updateStatus'])
            ->middleware('audit:UPDATE_VEHICLE_REQUEST_STATUS,vehicle_requests');

        // Approved requests waiting to be scheduled ('whereNumber' on the show route keeps "approved" from being read as an ID).
        Route::get('/vehicle-requests/approved', [VehicleRequestController::class, 'approved']);

        // A pending request is approved and assigned in one step: POST /allocations with approve_request = true.
        Route::post('/allocations', [AllocationController::class, 'store'])
            ->middleware('audit:APPROVE_AND_ALLOCATE_REQUEST,allocations');
        Route::put('/allocations/{allocation}', [AllocationController::class, 'update'])
            ->whereNumber('allocation')
            ->middleware('audit:UPDATE_ALLOCATION,allocations');
        Route::post('/allocation-drivers', [AllocationQuickAddController::class, 'storeDriver'])
            ->middleware('audit:CREATE_DRIVER,drivers');
        Route::post('/allocation-vehicles', [AllocationQuickAddController::class, 'storeVehicle'])
            ->middleware('audit:CREATE_VEHICLE,vehicles');

        // Date changes: reassign the date directly, or approve / disapprove a faculty request.
        Route::post('/allocations/{allocation}/reschedule', [RescheduleRequestController::class, 'reschedule'])
            ->whereNumber('allocation')
            ->middleware('audit:RESCHEDULE_ALLOCATION,allocations');
        Route::post('/reschedule-requests/{rescheduleRequest}/approve', [RescheduleRequestController::class, 'approve'])
            ->whereNumber('rescheduleRequest')
            ->middleware('audit:APPROVE_RESCHEDULE_REQUEST,reschedule_requests');
        Route::post('/reschedule-requests/{rescheduleRequest}/disapprove', [RescheduleRequestController::class, 'disapprove'])
            ->whereNumber('rescheduleRequest')
            ->middleware('audit:DISAPPROVE_RESCHEDULE_REQUEST,reschedule_requests');

        // Maintenance Monitoring
        Route::get('/maintenance-logs', [MaintenanceLogController::class, 'index']);
        Route::post('/maintenance-logs', [MaintenanceLogController::class, 'store'])
            ->middleware('audit:CREATE_MAINTENANCE,vehicle_maintenance');
        Route::patch('/maintenance-logs/{maintenanceLog}', [MaintenanceLogController::class, 'update'])
            ->middleware('audit:UPDATE_MAINTENANCE,vehicle_maintenance');

        // Fleet forms captured from the NVSU Motor Pool documents.
        Route::get('/post-travel-reports', [PostTravelReportController::class, 'index']);
        Route::post('/post-travel-reports', [PostTravelReportController::class, 'store'])
            ->middleware('audit:CREATE_POST_TRAVEL_REPORT,post_travel_reports');
        Route::patch('/post-travel-reports/{postTravelReport}', [PostTravelReportController::class, 'update'])
            ->middleware('audit:UPDATE_POST_TRAVEL_REPORT,post_travel_reports');

        Route::get('/fuel-usage-records', [FuelUsageRecordController::class, 'index']);
        Route::post('/fuel-usage-records', [FuelUsageRecordController::class, 'store'])
            ->middleware('audit:CREATE_FUEL_USAGE_RECORD,fuel_usage_records');
        Route::patch('/fuel-usage-records/{fuelUsageRecord}', [FuelUsageRecordController::class, 'update'])
            ->middleware('audit:UPDATE_FUEL_USAGE_RECORD,fuel_usage_records');

        Route::get('/preventive-maintenance-checklists', [PreventiveMaintenanceChecklistController::class, 'index']);
        Route::post('/preventive-maintenance-checklists', [PreventiveMaintenanceChecklistController::class, 'store'])
            ->middleware('audit:CREATE_PREVENTIVE_MAINTENANCE_CHECKLIST,preventive_maintenance_checklists');
        Route::patch('/preventive-maintenance-checklists/{preventiveMaintenanceChecklist}', [PreventiveMaintenanceChecklistController::class, 'update'])
            ->middleware('audit:UPDATE_PREVENTIVE_MAINTENANCE_CHECKLIST,preventive_maintenance_checklists');
        // The printable checklist (legal size), built by the backend.
        Route::get('/preventive-maintenance-checklists/{preventiveMaintenanceChecklist}/print', [PreventiveMaintenanceChecklistPrintController::class, 'show'])
            ->whereNumber('preventiveMaintenanceChecklist');
    });

    // ---------- Administrator only: accounts and audit history ----------
    Route::middleware('role:administrator')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('audit:CREATE_USER,users');
        Route::post('/admin-transfer', [AdministratorTransferController::class, 'store'])
            ->middleware('audit:TRANSFER_ADMINISTRATOR,users');
        Route::get('/users', [UserController::class, 'index']);
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->whereNumber('user')
            ->middleware('audit:UPDATE_USER,users');
        Route::delete('/users/{user}', [UserController::class, 'requestDeletion'])
            ->whereNumber('user')
            ->middleware('audit:DEACTIVATE_USER,users');
        Route::post('/users/{user}/cancel-deletion', [UserController::class, 'cancelDeletion'])
            ->whereNumber('user')
            ->middleware('audit:RESTORE_USER,users');
        // Audit history is read-only and administrator-only.
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
    });
});