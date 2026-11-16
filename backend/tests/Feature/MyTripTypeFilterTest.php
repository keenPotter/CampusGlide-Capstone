<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyTripTypeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_can_filter_own_trips_by_trip_type(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);
        $vehicle = Vehicle::create([
            'plate_number' => 'TYPE-001',
            'vehicle_model' => 'Test Van',
            'vehicle_type' => 'van',
        ]);
        $driver = Driver::create([
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'license_number' => 'TYPE-LICENSE-001',
            'license_expiry_date' => '2030-12-31',
        ]);

        $trips = [];
        foreach (['inclusive', 'exclusive'] as $tripType) {
            $request = VehicleRequest::factory()->create([
                'requester_id' => $faculty->id,
                'status' => 'approved',
                'trip_type' => $tripType,
                'trip_date' => now()->addDays(10)->toDateString(),
            ]);
            $trips[$tripType] = Trip::create([
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
        }

        $this->actingAs($faculty, 'sanctum')
            ->getJson('/api/my-trips?trip_type=inclusive')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $trips['inclusive']->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_my_trips_rejects_an_invalid_trip_type_filter(): void
    {
        $faculty = User::factory()->create(['role' => 'faculty']);

        $this->actingAs($faculty, 'sanctum')
            ->getJson('/api/my-trips?trip_type=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trip_type');
    }
}
