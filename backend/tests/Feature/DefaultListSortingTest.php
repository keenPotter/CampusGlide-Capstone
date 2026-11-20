<?php

namespace Tests\Feature;

use App\Models\Allocation;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DefaultListSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_requests_are_grouped_by_status_and_ordered_by_trip_date(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $staff = User::factory()->create(['role' => 'staff']);
        $faculty = User::factory()->create(['role' => 'faculty']);

        $pendingLater = VehicleRequest::factory()->create([
            'requester_id' => $faculty->id,
            'status' => 'pending',
            'trip_date' => now()->addDays(20)->toDateString(),
        ]);
        $pendingSooner = VehicleRequest::factory()->create([
            'requester_id' => $faculty->id,
            'status' => 'pending',
            'trip_date' => now()->addDays(10)->toDateString(),
        ]);
        $approvedRequest = VehicleRequest::factory()->create([
            'requester_id' => $faculty->id,
            'status' => 'approved',
            'trip_date' => now()->addDays(2)->toDateString(),
        ]);
        $disapprovedRequest = VehicleRequest::factory()->create([
            'requester_id' => null,
            'booking_source' => VehicleRequest::SOURCE_ON_SITE,
            'requester_name' => 'On-Site Requester',
            'requester_department' => 'Testing',
            'requester_contact' => '09123456789',
            'status' => 'disapproved',
            'trip_date' => now()->addDay()->toDateString(),
        ]);

        foreach ([$administrator, $staff] as $user) {
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/vehicle-requests')
                ->assertOk()
                ->assertJsonPath('data.0.id', $pendingSooner->id)
                ->assertJsonPath('data.1.id', $pendingLater->id)
                ->assertJsonPath('data.2.id', $approvedRequest->id)
                ->assertJsonPath('data.3.id', $disapprovedRequest->id);
        }

        $this->actingAs($faculty, 'sanctum')
            ->getJson('/api/vehicle-requests')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $pendingSooner->id)
            ->assertJsonPath('data.1.id', $pendingLater->id)
            ->assertJsonPath('data.2.id', $approvedRequest->id)
            ->assertJsonPath('data.0.booking_source', VehicleRequest::SOURCE_ONLINE)
            ->assertJsonPath('data.1.booking_source', VehicleRequest::SOURCE_ONLINE);
    }

    public function test_allocations_default_to_most_recently_updated_and_trips_remain_chronological(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $recentlyUpdated = $this->createAllocation($administrator, 'OLD', now()->addDays(10));
        $newlyAllocated = $this->createAllocation($administrator, 'NEW', now()->addDays(3));

        DB::table('allocations')->where('id', $recentlyUpdated->id)->update([
            'allocation_date' => now()->subDay(),
            'updated_at' => now()->addMinutes(2),
        ]);
        DB::table('allocations')->where('id', $newlyAllocated->id)->update([
            'allocation_date' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/allocations?per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.id', $recentlyUpdated->id)
            ->assertJsonPath('data.1.id', $newlyAllocated->id);

        $this->getJson('/api/trips?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newlyAllocated->trip_id)
            ->assertJsonPath('data.1.id', $recentlyUpdated->trip_id);
    }

    private function createAllocation(User $administrator, string $suffix, $tripDate): Allocation
    {
        $vehicle = Vehicle::create([
            'plate_number' => "SORT-{$suffix}",
            'vehicle_model' => 'Test Van',
            'vehicle_type' => 'Van',
            'status' => 'in_use',
            'is_active' => true,
        ]);
        $driver = Driver::create([
            'first_name' => 'Test',
            'last_name' => "Driver {$suffix}",
            'license_number' => "SORT-LICENSE-{$suffix}",
            'license_expiry_date' => now()->addYear()->toDateString(),
            'assigned_vehicle_id' => $vehicle->id,
            'is_available' => false,
        ]);
        $request = VehicleRequest::factory()->create([
            'status' => 'approved',
            'trip_date' => $tripDate->toDateString(),
            'trip_end_date' => $tripDate->toDateString(),
        ]);
        $trip = Trip::create([
            'vehicle_request_id' => $request->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_date' => $tripDate->toDateString(),
            'departure_time' => '09:00:00',
            'estimated_return_time' => '17:00:00',
            'destination' => "Destination {$suffix}",
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
