<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceLogIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_logs_are_paginated_with_global_summary(): void
    {
        /** @var User $administrator */
        $administrator = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/maintenance-logs?per_page=5')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('summary.total', 0)
            ->assertJsonPath('summary.upcoming', 0)
            ->assertJsonPath('summary.overdue', 0);
    }
}