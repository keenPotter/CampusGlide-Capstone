<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocationQuickAddValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_quick_add_requires_a_valid_capacity(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $payload = [
            'plate_number' => 'CAP-001',
            'vehicle_model' => 'Toyota HiAce',
        ];

        $this->actingAs($administrator, 'sanctum')
            ->postJson('/api/allocation-vehicles', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacity');

        $this->postJson('/api/allocation-vehicles', [...$payload, 'capacity' => 15])
            ->assertCreated()
            ->assertJsonPath('data.capacity', 15);
    }

    public function test_driver_quick_add_requires_an_eleven_digit_philippine_mobile_number(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $payload = [
            'name' => 'Jordan Lee',
            'contact_number' => '0912345678',
            'license_number' => 'LICENSE-CAP-001',
            'license_expiry_date' => now()->addYear()->toDateString(),
        ];

        $this->actingAs($administrator, 'sanctum')
            ->postJson('/api/allocation-drivers', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_number');

        $this->postJson('/api/allocation-drivers', [
            ...$payload,
            'contact_number' => '09123456789',
        ])
            ->assertCreated()
            ->assertJsonPath('data.contact_number', '09123456789');
    }
}
