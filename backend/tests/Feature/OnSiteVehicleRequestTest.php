<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnSiteVehicleRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_record_an_unassigned_on_site_request(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($administrator, 'sanctum')
            ->postJson('/api/vehicle-requests/on-site', $this->requestData())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.booking_source', 'on_site')
            ->assertJsonPath('data.requester_name', 'Jordan Lee')
            ->assertJsonPath('data.requester_department', 'Community Outreach')
            ->assertJsonPath('data.requester_contact', '09123456789')
            ->assertJsonPath('data.requested_by', null);

        $requestId = $response->json('data.id');
        $this->assertDatabaseHas('vehicle_requests', [
            'id' => $requestId,
            'requester_id' => null,
            'booking_source' => 'on_site',
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('trips', ['vehicle_request_id' => $requestId]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'CREATE_ON_SITE_BOOKING',
            'target_table' => 'vehicle_requests',
            'target_id' => (string) $requestId,
        ]);

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/vehicle-requests?search=Community%20Outreach')
            ->assertOk()
            ->assertJsonPath('data.0.requester_name', 'Jordan Lee');
    }

    public function test_administrator_can_immediately_schedule_an_on_site_request(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $vehicle = Vehicle::create([
            'plate_number' => 'ONSITE-001',
            'vehicle_model' => 'Campus Van',
            'vehicle_type' => 'Van',
            'status' => 'available',
            'is_active' => true,
        ]);
        $driver = Driver::create([
            'first_name' => 'Taylor',
            'last_name' => 'Driver',
            'license_number' => 'ONSITE-LICENSE-001',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'is_available' => true,
        ]);

        $response = $this->actingAs($administrator, 'sanctum')
            ->postJson('/api/vehicle-requests/on-site', [
                ...$this->requestData(),
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.booking_source', 'on_site');

        $requestId = $response->json('data.id');
        $trip = Trip::where('vehicle_request_id', $requestId)->firstOrFail();
        $allocation = Allocation::where('trip_id', $trip->id)->firstOrFail();

        $this->assertSame('scheduled', $trip->trip_status);
        $this->assertDatabaseHas('vehicle_requests', [
            'id' => $requestId,
            'requester_id' => null,
            'status' => 'approved',
            'approved_by' => $administrator->id,
        ]);
        $this->assertDatabaseHas('allocations', [
            'trip_id' => $trip->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'allocated_by' => $administrator->id,
        ]);
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => 'in_use',
        ]);
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'is_available' => false,
            'assigned_vehicle_id' => $vehicle->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'CREATE_ON_SITE_BOOKING',
            'target_table' => 'vehicle_requests',
            'target_id' => (string) $requestId,
        ]);
        $this->getJson("/api/allocations/{$allocation->id}")
            ->assertOk()
            ->assertJsonPath('data.vehicle_request.booking_source', 'on_site')
            ->assertJsonPath('data.vehicle_request.requester', 'Jordan Lee')
            ->assertJsonPath('data.vehicle_request.requester_department', 'Community Outreach')
            ->assertJsonPath('data.vehicle_request.requester_contact', '09123456789');
        $this->get("/api/allocations/{$allocation->id}/print")
            ->assertOk()
            ->assertSee('On-Site')
            ->assertSee('Community Outreach')
            ->assertSee('09123456789');
        $this->getJson('/api/trips?search=Jordan')
            ->assertOk()
            ->assertJsonPath('data.0.vehicle_request.requester_name', 'Jordan Lee');

    }

    public function test_vehicle_and_driver_must_be_assigned_together(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $vehicle = Vehicle::create([
            'plate_number' => 'ONSITE-002',
            'vehicle_model' => 'Campus Van',
            'vehicle_type' => 'Van',
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->actingAs($administrator, 'sanctum')
            ->postJson('/api/vehicle-requests/on-site', [
                ...$this->requestData(),
                'vehicle_id' => $vehicle->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('driver_id');

        $this->assertDatabaseCount('vehicle_requests', 0);
    }

    private function requestData(): array
    {
        $tripDate = now()->addDays(3)->toDateString();

        return [
            'requester_name' => 'Jordan Lee',
            'requester_department' => 'Community Outreach',
            'requester_contact' => '09123456789',
            'destination' => 'Regional Office',
            'purpose' => 'Community outreach visit',
            'trip_date' => $tripDate,
            'trip_end_date' => $tripDate,
            'trip_type' => 'inclusive',
            'departure_time' => '09:00',
            'estimated_return_time' => '16:00',
            'passengers' => 'Outreach team',
            'number_of_passengers' => 3,
        ];
    }
}
