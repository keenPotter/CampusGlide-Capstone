<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocationWorkflowSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_options_only_returns_fleet_choices(): void
    {
        /** @var User $administrator */
        $administrator = User::factory()->create(['role' => 'administrator']);
        VehicleRequest::factory()->create(['status' => 'pending']);

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/allocation-options?skip_completion=1')
            ->assertOk()
            ->assertJsonStructure(['vehicles', 'drivers'])
            ->assertJsonMissingPath('requests');
    }
}