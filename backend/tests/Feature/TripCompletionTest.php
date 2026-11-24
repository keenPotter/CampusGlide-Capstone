<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\RescheduleRequest;
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

    public function test_faculty_request_list_keeps_completed_trip_out_of_approved_filter(): void
    {
        $requester = User::factory()->create(['role' => 'faculty']);
        $vehicle = Vehicle::create([
            'plate_number' => 'TEST-002',
            'vehicle_model' => 'Test Van',
            'vehicle_type' => 'van',
            'status' => 'in_use',
        ]);
        $driver = Driver::create([
            'first_name' => 'Past',
            'last_name' => 'Trip Driver',
            'license_number' => 'TEST-LICENSE-002',
            'license_expiry_date' => '2030-12-31',
            'assigned_vehicle_id' => $vehicle->id,
            'is_available' => false,
        ]);
        $request = VehicleRequest::factory()->create([
            'requester_id' => $requester->id,
            'status' => 'approved',
            'trip_date' => '2026-11-20',
            'trip_end_date' => '2026-11-20',
            'departure_time' => '08:00:00',
            'estimated_return_time' => '17:00:00',
        ]);
        $trip = Trip::create([
            'vehicle_request_id' => $request->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_date' => '2026-11-20',
            'departure_time' => '08:00:00',
            'estimated_return_time' => '17:00:00',
            'destination' => 'Past trip destination',
            'purpose' => 'Past trip purpose',
            'trip_status' => 'scheduled',
        ]);

        $this->travelTo(Carbon::parse('2026-11-21 10:00:00', 'Asia/Manila'));
        $this->actingAs($requester, 'sanctum')
            ->getJson('/api/vehicle-requests?status=approved')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');

        $this->assertSame('completed', $trip->fresh()->trip_status);
        $this->assertSame('available', $vehicle->fresh()->status);
        $this->assertTrue($driver->fresh()->is_available);

        $this->actingAs($requester, 'sanctum')
            ->getJson('/api/vehicle-requests?status=completed')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $request->id)
            ->assertJsonPath('data.0.trip_status', 'completed');
    }

    public function test_pending_date_change_freezes_completion_until_disapproval(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $vehicle = Vehicle::create([
            'plate_number' => 'TEST-003',
            'vehicle_model' => 'Test Van',
            'vehicle_type' => 'van',
            'status' => 'in_use',
        ]);
        $driver = Driver::create([
            'first_name' => 'Pending',
            'last_name' => 'Trip Driver',
            'license_number' => 'TEST-LICENSE-003',
            'license_expiry_date' => '2030-12-31',
            'assigned_vehicle_id' => $vehicle->id,
            'is_available' => false,
        ]);
        $request = VehicleRequest::factory()->create([
            'requester_id' => $faculty->id,
            'status' => 'approved',
            'trip_date' => '2026-11-20',
            'trip_end_date' => '2026-11-20',
            'estimated_return_time' => '17:00:00',
        ]);
        $trip = Trip::create([
            'vehicle_request_id' => $request->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_date' => '2026-11-20',
            'departure_time' => '08:00:00',
            'estimated_return_time' => '17:00:00',
            'destination' => 'Pending trip destination',
            'purpose' => 'Pending trip purpose',
            'trip_status' => 'scheduled',
        ]);
        $dateChange = RescheduleRequest::create([
            'trip_id' => $trip->id,
            'requested_by' => $faculty->id,
            'old_date' => '2026-11-20',
            'new_date' => '2026-12-01',
            'old_trip_end_date' => '2026-11-20',
            'new_trip_end_date' => '2026-12-01',
            'reason' => 'Requesting a later travel date',
            'status' => 'pending',
        ]);

        $this->travelTo(Carbon::parse('2026-11-21 10:00:00', 'Asia/Manila'));
        $this->assertSame(0, app(AllocationService::class)->completeFinishedTrips());
        $this->assertSame('scheduled', $trip->fresh()->trip_status);
        $this->assertSame('in_use', $vehicle->fresh()->status);
        $this->assertFalse($driver->fresh()->is_available);

        $this->actingAs($administrator, 'sanctum')
            ->postJson("/api/reschedule-requests/{$dateChange->id}/disapprove", [
                'admin_reason' => 'The requested date change is not approved.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'disapproved');

        $this->assertSame('completed', $trip->fresh()->trip_status);
        $this->assertSame('available', $vehicle->fresh()->status);
        $this->assertTrue($driver->fresh()->is_available);
    }
}
