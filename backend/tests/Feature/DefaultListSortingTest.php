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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DefaultListSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_requests_are_ordered_by_most_recent_submission_first(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $staff = User::factory()->create(['role' => 'staff']);
        $faculty = User::factory()->create(['role' => 'faculty']);

        $pendingLater = VehicleRequest::factory()->create([
            'requester_id' => $faculty->id,
            'status' => 'pending',
            'trip_date' => now()->addDays(20)->toDateString(),
            'created_at' => now()->subDays(3),
        ]);
        $pendingSooner = VehicleRequest::factory()->create([
            'requester_id' => $faculty->id,
            'status' => 'pending',
            'trip_date' => now()->addDays(10)->toDateString(),
            'created_at' => now()->subDay(),
        ]);
        $approvedRequest = VehicleRequest::factory()->create([
            'requester_id' => $faculty->id,
            'status' => 'approved',
            'trip_date' => now()->addDays(2)->toDateString(),
            'created_at' => now()->subDays(2),
        ]);
        $disapprovedRequest = VehicleRequest::factory()->create([
            'requester_id' => null,
            'booking_source' => VehicleRequest::SOURCE_ON_SITE,
            'requester_name' => 'On-Site Requester',
            'requester_department' => 'Testing',
            'requester_contact' => '09123456789',
            'status' => 'disapproved',
            'trip_date' => now()->addDay()->toDateString(),
            'created_at' => now(),
        ]);

        foreach ([$administrator, $staff] as $user) {
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/vehicle-requests')
                ->assertOk()
                ->assertJsonPath('data.0.id', $disapprovedRequest->id)
                ->assertJsonPath('data.1.id', $pendingSooner->id)
                ->assertJsonPath('data.2.id', $approvedRequest->id)
                ->assertJsonPath('data.3.id', $pendingLater->id);
        }

        $this->actingAs($faculty, 'sanctum')
            ->getJson('/api/vehicle-requests')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $pendingSooner->id)
            ->assertJsonPath('data.1.id', $approvedRequest->id)
            ->assertJsonPath('data.2.id', $pendingLater->id)
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

    public function test_pending_date_change_requests_are_ordered_by_newest_submission_first(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $olderAllocation = $this->createAllocation($administrator, 'OLDER-CHANGE', now()->addDays(5));
        $newerAllocation = $this->createAllocation($administrator, 'NEWER-CHANGE', now()->addDays(20));

        $olderRequest = RescheduleRequest::create([
            'trip_id' => $olderAllocation->trip_id,
            'requested_by' => $administrator->id,
            'old_date' => now()->addDays(5)->toDateString(),
            'new_date' => now()->addDays(6)->toDateString(),
            'reason' => 'Older submission',
            'status' => 'pending',
        ]);
        $newerRequest = RescheduleRequest::create([
            'trip_id' => $newerAllocation->trip_id,
            'requested_by' => $administrator->id,
            'old_date' => now()->addDays(20)->toDateString(),
            'new_date' => now()->addDays(25)->toDateString(),
            'reason' => 'Newer submission',
            'status' => 'pending',
        ]);
        DB::table('reschedule_requests')->where('id', $olderRequest->id)
            ->update(['created_at' => now()->subDays(2)]);
        DB::table('reschedule_requests')->where('id', $newerRequest->id)
            ->update(['created_at' => now()->subDay()]);

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/trip-modification-requests?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newerRequest->id)
            ->assertJsonPath('data.1.id', $olderRequest->id);
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
