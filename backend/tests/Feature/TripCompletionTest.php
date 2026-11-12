<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use App\Services\AllocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiday_trip_is_completed_only_after_its_final_day_and_return_time(): void
    {
        /** @var User $requester */
        $requester = User::factory()->create(['role' => 'faculty']);
        $vehicle = Vehicle::create([
            'plate_number' => 'TEST-001',
            'vehicle_model' => 'Test Van',
            'vehicle_type' => 'van',
            'status' => 'in_use',
        ]);
        $driver = Driver::create([
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'license_number' => 'TEST-LICENSE-001',
            'license_expiry_date' => '2030-12-31',
            'assigned_vehicle_id' => $vehicle->id,
            'is_available' => false,
        ]);
        $request = VehicleRequest::factory()->create([
            'requester_id' => $requester->id,
            'status' => 'approved',
            'trip_date' => '2026-11-20',
            'trip_end_date' => '2026-12-01',
            'estimated_return_time' => '17:00:00',
        ]);
        $trip = Trip::create([
            'vehicle_request_id' => $request->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_date' => '2026-11-20',
            'departure_time' => '08:00:00',
            'estimated_return_time' => '17:00:00',
            'destination' => 'Test destination',
            'purpose' => 'Test purpose',
            'trip_status' => 'scheduled',
        ]);
        $service = app(AllocationService::class);

        $this->travelTo(Carbon::parse('2026-11-25 18:00:00', 'Asia/Manila'));
        $service->completeFinishedTrips();
        $this->assertSame('scheduled', $trip->fresh()->trip_status);

        $this->travelTo(Carbon::parse('2026-12-01 16:59:59', 'Asia/Manila'));
        $service->completeFinishedTrips();
        $this->assertSame('scheduled', $trip->fresh()->trip_status);

        $this->travelTo(Carbon::parse('2026-12-01 17:00:00', 'Asia/Manila'));
        $service->completeFinishedTrips();
        $this->assertSame('completed', $trip->fresh()->trip_status);
        $this->assertSame('available', $vehicle->fresh()->status);
        $this->assertTrue($driver->fresh()->is_available);
    }
}
