<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllocationWorkflowSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_needs_allocation_includes_pending_and_approved_unassigned_requests(): void
    {
        /** @var User $administrator */
        $administrator = User::factory()->create(['role' => 'administrator']);
        $approvedRequest = VehicleRequest::factory()->create(['status' => 'approved']);
        $pendingRequest = VehicleRequest::factory()->create(['status' => 'pending']);

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/allocation-options?skip_completion=1')
            ->assertOk()
            ->assertJsonCount(2, 'requests')
            ->assertJsonFragment(['id' => $approvedRequest->id, 'status' => 'approved'])
            ->assertJsonFragment(['id' => $pendingRequest->id, 'status' => 'pending']);
    }
}