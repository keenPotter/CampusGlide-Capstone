<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnSiteVehicleRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_record_an_on_site_request_with_a_trip_type(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($administrator, 'sanctum')
            ->postJson('/api/vehicle-requests/on-site', [
                'requester_name' => 'Taylor Faculty',
                'requester_department' => 'Science',
                'requester_contact' => '09123456789',
                'destination' => 'Bayombong',
                'purpose' => 'Campus meeting',
                'trip_date' => now()->addDay()->toDateString(),
                'trip_end_date' => now()->addDay()->toDateString(),
                'trip_type' => 'exclusive',
                'departure_time' => '07:30',
                'estimated_return_time' => '17:00',
                'passengers' => 'Taylor Faculty',
                'number_of_passengers' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.booking_source', 'on_site')
            ->assertJsonPath('data.requester_name', 'Taylor Faculty')
            ->assertJsonPath('data.trip_type', 'exclusive')
            ->assertJsonPath('data.requested_by', null);
    }
}
