<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Driver;
use App\Models\RescheduleRequest;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffReadOnlyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_mutate_allocations_or_approve_requests(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $administrator = User::factory()->create(['role' => 'administrator']);
        [$vehicleRequest, $trip, $allocation, $rescheduleRequest] = $this->createScheduledAllocation($administrator);
        $driverCount = Driver::count();
        $vehicleCount = Vehicle::count();

        $mutations = [
            ['PUT', "/api/allocations/{$allocation->id}", ['trip_status' => 'completed']],
            ['POST', "/api/allocations/{$allocation->id}/reschedule", []],
            ['POST', "/api/reschedule-requests/{$rescheduleRequest->id}/approve", []],
            ['POST', "/api/reschedule-requests/{$rescheduleRequest->id}/disapprove", []],
            ['POST', '/api/allocations', []],
            ['POST', "/api/allocations/{$allocation->id}/cancel", []],
            ['POST', '/api/allocation-drivers', []],
            ['POST', '/api/allocation-vehicles', []],
            ['PATCH', "/api/vehicle-requests/{$vehicleRequest->id}/status", []],
            ['POST', '/api/vehicle-requests/on-site', []],
            ['DELETE', "/api/users/{$administrator->id}", []],
        ];

        foreach ($mutations as [$method, $uri, $payload]) {
            $this->actingAs($staff, 'sanctum')
                ->json($method, $uri, $payload)
                ->assertForbidden();
        }

        $this->assertSame('scheduled', $trip->fresh()->trip_status);
        $this->assertSame('pending', $rescheduleRequest->fresh()->status);
        $this->assertSame($driverCount, Driver::count());
        $this->assertSame($vehicleCount, Vehicle::count());
    }

    public function test_staff_can_view_and_send_an_approved_trip_ticket_to_an_administrator(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $administrator = User::factory()->create(['role' => 'administrator']);
        [, $trip, $allocation] = $this->createScheduledAllocation($administrator);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/allocations')
            ->assertOk();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/allocation-options')
            ->assertOk();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/trips')
            ->assertOk();

        $this->actingAs($staff, 'sanctum')
            ->getJson("/api/trips/{$trip->id}")
            ->assertOk();

        $this->actingAs($staff, 'sanctum')
            ->postJson("/api/trips/{$trip->id}/view")
            ->assertOk()
            ->assertJsonPath('viewed', true);

        $this->actingAs($staff, 'sanctum')
            ->get("/api/allocations/{$allocation->id}/print")
            ->assertOk();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/trip-request-shares', [
                'allocation_id' => $allocation->id,
                'recipient_id' => $administrator->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('trip_request_shares', [
            'allocation_id' => $allocation->id,
            'sender_id' => $staff->id,
            'recipient_id' => $administrator->id,
        ]);
    }

    /**
     * @return array{VehicleRequest, Trip, Allocation, RescheduleRequest}
     */
    private function createScheduledAllocation(User $administrator): array
    {
        $vehicle = Vehicle::create([
            'plate_number' => 'STAFF-001',
            'vehicle_model' => 'Test Van',
            'vehicle_type' => 'van',
        ]);
        $driver = Driver::create([
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'license_number' => 'STAFF-LICENSE-001',
            'license_expiry_date' => '2030-12-31',
        ]);
        $vehicleRequest = VehicleRequest::factory()->create([
            'status' => 'approved',
            'trip_date' => now()->addDays(2)->toDateString(),
            'trip_end_date' => now()->addDays(2)->toDateString(),
        ]);
        $trip = Trip::create([
            'vehicle_request_id' => $vehicleRequest->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_date' => $vehicleRequest->trip_date,
            'departure_time' => $vehicleRequest->departure_time,
            'estimated_return_time' => $vehicleRequest->estimated_return_time,
            'destination' => $vehicleRequest->destination,
            'purpose' => $vehicleRequest->purpose,
            'trip_status' => 'scheduled',
        ]);
        $allocation = Allocation::create([
            'trip_id' => $trip->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'allocated_by' => $administrator->id,
            'allocation_date' => now(),
        ]);
        $rescheduleRequest = RescheduleRequest::create([
            'trip_id' => $trip->id,
            'requested_by' => $vehicleRequest->requester_id,
            'old_date' => $vehicleRequest->trip_date,
            'new_date' => now()->addDays(4)->toDateString(),
            'reason' => 'Schedule change',
            'status' => 'pending',
        ]);

        return [$vehicleRequest, $trip, $allocation, $rescheduleRequest];
    }
}
