<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_cancel_a_scheduled_trip_and_filter_cancelled_allocations(): void
    {
        $administrator = User::factory()->create([
            'role' => 'administrator',
            'first_name' => 'Admin',
            'last_name' => 'User',
        ]);
        $allocation = $this->createScheduledAllocation($administrator);

        $this->actingAs($administrator, 'sanctum')
            ->postJson("/api/allocations/{$allocation->id}/cancel", [
                'reason' => 'The event was postponed.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.trip.cancellation_reason', 'The event was postponed.')
            ->assertJsonPath('data.trip.cancelled_by', 'Admin User')
            ->assertJsonStructure(['data' => ['trip' => ['cancelled_at']]]);

        $this->assertDatabaseHas('trips', [
            'id' => $allocation->trip_id,
            'trip_status' => 'cancelled',
            'cancellation_reason' => 'The event was postponed.',
            'cancelled_by' => $administrator->id,
        ]);
        $this->assertDatabaseHas('vehicles', [
            'id' => $allocation->vehicle_id,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('drivers', [
            'id' => $allocation->driver_id,
            'is_available' => true,
            'assigned_vehicle_id' => null,
        ]);

        $this->get("/api/allocations/{$allocation->id}/print")
            ->assertOk()
            ->assertSee('TRIP CANCELLED')
            ->assertSee('The event was postponed.')
            ->assertSee('Admin User');

        $this->getJson('/api/allocations?status=cancelled')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'cancelled');

        $this->getJson('/api/allocations?status=scheduled')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/trips?status=cancelled')
            ->assertOk()
            ->assertJsonPath('data.0.trip_status', 'cancelled')
            ->assertJsonPath('data.0.cancellation_reason', 'The event was postponed.')
            ->assertJsonPath('data.0.cancelled_by', 'Admin User');
    }

    public function test_only_scheduled_trips_can_be_cancelled(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $allocation = $this->createScheduledAllocation($administrator);
        Trip::whereKey($allocation->trip_id)->update(['trip_status' => 'in_progress']);

        $this->actingAs($administrator, 'sanctum')
            ->postJson("/api/allocations/{$allocation->id}/cancel", [
                'reason' => 'The event was postponed.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('trips', [
            'id' => $allocation->trip_id,
            'trip_status' => 'in_progress',
            'cancellation_reason' => null,
        ]);
    }

    private function createScheduledAllocation(User $administrator): Allocation
    {
        $vehicle = Vehicle::create([
            'plate_number' => 'TEST-001',
            'vehicle_model' => 'Test Van',
            'vehicle_type' => 'Van',
            'status' => 'in_use',
            'is_active' => true,
        ]);
        $driver = Driver::create([
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'license_number' => 'TEST-LICENSE-001',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'assigned_vehicle_id' => $vehicle->id,
            'is_available' => false,
        ]);
        $request = VehicleRequest::factory()->create([
            'status' => 'approved',
            'trip_date' => now()->addDays(10)->toDateString(),
            'trip_end_date' => now()->addDays(10)->toDateString(),
        ]);
        $trip = Trip::create([
            'vehicle_request_id' => $request->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_date' => $request->trip_date,
            'departure_time' => '09:00:00',
            'estimated_return_time' => '17:00:00',
            'destination' => 'Test Destination',
            'purpose' => 'Test purpose',
            'trip_status' => 'scheduled',
        ]);

        return Allocation::create([
            'trip_id' => $trip->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'allocated_by' => $administrator->id,
            'allocation_date' => now(),
        ]);
    }
}
