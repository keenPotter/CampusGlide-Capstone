<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripViewedStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewed_trip_state_is_persisted_per_user_and_idempotent(): void
    {
        /** @var User $administrator */
        $administrator = User::factory()->create(['role' => 'administrator']);
        $vehicle = Vehicle::create([
            'plate_number' => 'VIEW-001',
            'vehicle_model' => 'Test Van',
            'vehicle_type' => 'van',
        ]);
        $driver = Driver::create([
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'license_number' => 'VIEW-LICENSE-001',
            'license_expiry_date' => '2030-12-31',
        ]);
        $request = VehicleRequest::factory()->create(['status' => 'approved']);
        $trip = Trip::create([
            'vehicle_request_id' => $request->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_date' => $request->trip_date,
            'departure_time' => $request->departure_time,
            'estimated_return_time' => $request->estimated_return_time,
            'destination' => $request->destination,
            'purpose' => $request->purpose,
            'trip_status' => 'scheduled',
        ]);

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/trip-views')
            ->assertOk()
            ->assertJsonPath('trip_ids', []);

        $this->actingAs($administrator, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/view")
            ->assertOk()
            ->assertJsonPath('trip_id', $trip->id)
            ->assertJsonPath('viewed', true);

        $this->actingAs($administrator, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/view")
            ->assertOk();

        $this->assertDatabaseCount('trip_views', 1);
        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/trip-views')
            ->assertOk()
            ->assertJsonPath('trip_ids', [$trip->id]);

        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/trip-views')
            ->assertOk()
            ->assertJsonPath('trip_ids', []);
    }
}