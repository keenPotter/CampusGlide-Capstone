<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleRequestMonthYearFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_and_year_filters_work_independently_and_together(): void
    {
        /** @var User $administrator */
        $administrator = User::factory()->create(['role' => 'administrator']);

        $january2026 = VehicleRequest::factory()->create([
            'trip_date' => '2026-01-15',
            'trip_end_date' => '2026-01-15',
        ]);
        VehicleRequest::factory()->create([
            'trip_date' => '2026-02-15',
            'trip_end_date' => '2026-02-15',
        ]);
        VehicleRequest::factory()->create([
            'trip_date' => '2025-01-15',
            'trip_end_date' => '2025-01-15',
        ]);

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/vehicle-requests?month=1&year=2026')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $january2026->id);

        $this->getJson('/api/vehicle-requests?month=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/vehicle-requests?year=2026')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}