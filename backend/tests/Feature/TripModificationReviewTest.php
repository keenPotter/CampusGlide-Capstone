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

class TripModificationReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_can_submit_date_change_request_with_trip_type_and_authorized_passengers(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $oldDate = now()->addDays(5)->toDateString();
        $newDate = now()->addDays(15)->toDateString();

        $vehicle = Vehicle::create([
            'plate_number' => 'DATE-001',
            'vehicle_model' => 'Date change vehicle',
            'vehicle_type' => 'Van',
            'capacity' => 12,
            'status' => 'in_use',
            'is_active' => true,
        ]);
        $driver = Driver::create([
            'first_name' => 'Date',
            'last_name' => 'Change',
            'license_number' => 'DATE-LICENSE',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'assigned_vehicle_id' => $vehicle->id,
            'is_available' => false,
        ]);
        $vehicleRequest = VehicleRequest::factory()->create([
            'requester_id' => $faculty->id,
            'status' => 'approved',
            'trip_date' => $oldDate,
            'trip_end_date' => $oldDate,
            'departure_time' => '08:00:00',
            'estimated_return_time' => '17:00:00',
            'destination' => 'Original destination',
            'purpose' => 'Original purpose',
            'trip_type' => 'inclusive',
            'passengers' => 'Faculty group',
            'number_of_passengers' => 3,
        ]);
        $trip = Trip::create([
            'vehicle_request_id' => $vehicleRequest->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_date' => $oldDate,
            'departure_time' => '08:00:00',
            'estimated_return_time' => '17:00:00',
            'destination' => 'Original destination',
            'purpose' => 'Original purpose',
            'trip_status' => 'scheduled',
        ]);
        $allocation = Allocation::create([
            'trip_id' => $trip->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'allocated_by' => $administrator->id,
            'allocation_date' => now(),
        ]);

        $this->actingAs($faculty, 'sanctum')
            ->postJson("/api/allocations/{$allocation->id}/reschedule-requests", [
                'new_date' => $newDate,
                'reason' => 'The meeting was moved.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.old_trip_type', 'inclusive')
            ->assertJsonPath('data.new_trip_type', 'inclusive')
            ->assertJsonPath('data.old_authorized_passengers', 'Faculty group')
            ->assertJsonPath('data.new_authorized_passengers', 'Faculty group');
    }

    public function test_admin_can_review_all_requested_fields_and_reassign_resources_on_approval(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $faculty = User::factory()->create(['role' => 'faculty']);
        $oldDate = now()->addDays(5)->toDateString();
        $newDate = now()->addDays(15)->toDateString();
        $endDate = now()->addDays(7)->toDateString();
        $newEndDate = now()->addDays(18)->toDateString();

        $oldVehicle = Vehicle::create([
            'plate_number' => 'OLD-001',
            'vehicle_model' => 'Old vehicle',
            'vehicle_type' => 'Van',
            'capacity' => 12,
            'status' => 'in_use',
            'is_active' => true,
        ]);
        $newVehicle = Vehicle::create([
            'plate_number' => 'NEW-001',
            'vehicle_model' => 'Replacement vehicle',
            'vehicle_type' => 'Van',
            'capacity' => 12,
            'status' => 'available',
            'is_active' => true,
        ]);
        $oldDriver = Driver::create([
            'first_name' => 'Old',
            'last_name' => 'Driver',
            'license_number' => 'OLD-LICENSE',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'assigned_vehicle_id' => $oldVehicle->id,
            'is_available' => false,
        ]);
        $newDriver = Driver::create([
            'first_name' => 'New',
            'last_name' => 'Driver',
            'license_number' => 'NEW-LICENSE',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'is_available' => true,
        ]);
        $vehicleRequest = VehicleRequest::factory()->create([
            'requester_id' => $faculty->id,
            'status' => 'approved',
            'trip_date' => $oldDate,
            'trip_end_date' => $endDate,
            'departure_time' => '08:00:00',
            'estimated_return_time' => '17:00:00',
            'destination' => 'Original destination',
            'purpose' => 'Original purpose',
            'trip_type' => 'inclusive',
            'passengers' => 'Marife Aliaga and 2 faculty members',
            'number_of_passengers' => 3,
        ]);
        $trip = Trip::create([
            'vehicle_request_id' => $vehicleRequest->id,
            'vehicle_id' => $oldVehicle->id,
            'driver_id' => $oldDriver->id,
            'trip_date' => $oldDate,
            'departure_time' => '08:00:00',
            'estimated_return_time' => '17:00:00',
            'destination' => 'Original destination',
            'purpose' => 'Original purpose',
            'trip_status' => 'scheduled',
        ]);
        $allocation = Allocation::create([
            'trip_id' => $trip->id,
            'vehicle_id' => $oldVehicle->id,
            'driver_id' => $oldDriver->id,
            'allocated_by' => $administrator->id,
            'allocation_date' => now(),
        ]);
        $this->actingAs($faculty, 'sanctum')
            ->putJson("/api/vehicle-requests/{$vehicleRequest->id}/edit", [
                'destination' => 'Updated destination',
                'purpose' => 'Updated purpose',
                'trip_date' => $newDate,
                'trip_end_date' => $newEndDate,
                'trip_type' => 'exclusive',
                'departure_time' => '09:00',
                'estimated_return_time' => '18:00',
                'passengers' => 'Marife Aliaga and 4 faculty members',
                'number_of_passengers' => 5,
                'edit_reason' => 'Updated schedule and trip details',
            ])
            ->assertOk();

        $modification = RescheduleRequest::where('trip_id', $trip->id)->firstOrFail();

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/trip-modification-requests?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.new_destination', 'Updated destination')
            ->assertJsonPath('data.0.new_purpose', 'Updated purpose')
            ->assertJsonPath('data.0.new_number_of_passengers', 5)
            ->assertJsonPath('data.0.new_trip_end_date', $newEndDate)
            ->assertJsonPath('data.0.new_estimated_return_time', '18:00:00')
            ->assertJsonPath('data.0.new_trip_type', 'exclusive')
            ->assertJsonPath('data.0.new_authorized_passengers', 'Marife Aliaga and 4 faculty members')
            ->assertJsonPath('data.0.trip.vehicle.id', $oldVehicle->id)
            ->assertJsonPath('data.0.trip.driver.id', $oldDriver->id);

        $this->actingAs($administrator, 'sanctum')
            ->postJson("/api/trip-modification-requests/{$modification->id}/approve", [
                'vehicle_id' => $newVehicle->id,
                'driver_id' => $newDriver->id,
                'admin_reason' => 'The updated schedule is approved.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.admin_reason', 'The updated schedule is approved.');

        $this->actingAs($faculty, 'sanctum')
            ->getJson('/api/reschedule-requests')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'approved')
            ->assertJsonPath('data.0.admin_reason', 'The updated schedule is approved.');

        $trip->refresh();
        $vehicleRequest->refresh();
        $allocation->refresh();
        $oldVehicle->refresh();
        $newVehicle->refresh();
        $oldDriver->refresh();
        $newDriver->refresh();

        $this->assertSame($newDate, $trip->trip_date->toDateString());
        $this->assertSame('09:00:00', $trip->departure_time);
        $this->assertSame('18:00:00', $trip->estimated_return_time);
        $this->assertSame('Updated destination', $trip->destination);
        $this->assertSame('Updated purpose', $trip->purpose);
        $this->assertSame($newEndDate, $vehicleRequest->trip_end_date->toDateString());
        $this->assertSame(5, $vehicleRequest->number_of_passengers);
        $this->assertSame('exclusive', $vehicleRequest->trip_type);
        $this->assertSame('Marife Aliaga and 4 faculty members', $vehicleRequest->passengers);
        $this->assertSame($newVehicle->id, $allocation->vehicle_id);
        $this->assertSame($newDriver->id, $allocation->driver_id);
        $this->assertSame('available', $oldVehicle->status);
        $this->assertSame('in_use', $newVehicle->status);
        $this->assertTrue($oldDriver->is_available);
        $this->assertFalse($newDriver->is_available);
        $this->assertSame($newVehicle->id, $newDriver->assigned_vehicle_id);
    }
}
