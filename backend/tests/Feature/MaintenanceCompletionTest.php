<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_records_today_and_preserves_scheduled_and_next_due_dates(): void
    {
        $vehicle = $this->createVehicle();
        $scheduledDate = now()->addDays(5)->toDateString();
        $nextDueDate = now()->addMonths(6)->toDateString();
        $log = $this->createLog($vehicle, [
            'maintenance_date' => $scheduledDate,
            'next_due_date' => $nextDueDate,
        ]);
        $administrator = $this->createAdministrator();

        $this->actingAs($administrator, 'sanctum')
            ->patchJson("/api/maintenance-logs/{$log->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.completion_date', now()->toDateString());

        $this->assertDatabaseHas('vehicle_maintenance', [
            'id' => $log->id,
            'maintenance_date' => $scheduledDate,
            'completion_date' => now()->toDateString(),
            'next_due_date' => $nextDueDate,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => 'available',
            'last_maintenance_date' => now()->toDateString(),
            'next_maintenance_date' => $nextDueDate,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'COMPLETE_MAINTENANCE',
            'target_table' => 'vehicle_maintenance',
            'target_id' => (string) $log->id,
        ]);
    }

    public function test_new_completed_records_receive_today_as_the_completion_date(): void
    {
        $vehicle = $this->createVehicle();
        $scheduledDate = now()->addDays(5)->toDateString();

        $this->actingAs($this->createAdministrator(), 'sanctum')
            ->postJson('/api/maintenance-logs', [
                'vehicle_id' => $vehicle->id,
                'type' => 'inspection',
                'date_performed' => $scheduledDate,
                'next_due_date' => now()->addMonths(6)->toDateString(),
                'status' => 'completed',
            ])
            ->assertCreated()
            ->assertJsonPath('data.completion_date', now()->toDateString())
            ->assertJsonPath('data.date_performed', $scheduledDate);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => 'available',
        ]);
    }

    public function test_vehicle_stays_in_maintenance_when_another_record_is_in_progress(): void
    {
        $vehicle = $this->createVehicle();
        $log = $this->createLog($vehicle);
        $this->createLog($vehicle);

        $this->actingAs($this->createAdministrator(), 'sanctum')
            ->patchJson("/api/maintenance-logs/{$log->id}", ['status' => 'completed'])
            ->assertOk();

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => 'maintenance',
        ]);
    }

    public function test_completion_does_not_change_a_vehicle_out_of_another_status(): void
    {
        $vehicle = $this->createVehicle('in_use');
        $log = $this->createLog($vehicle);

        $this->actingAs($this->createAdministrator(), 'sanctum')
            ->patchJson("/api/maintenance-logs/{$log->id}", ['status' => 'completed'])
            ->assertOk();

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'status' => 'in_use',
        ]);
    }

    public function test_editing_an_already_completed_record_does_not_rewrite_its_completion_date(): void
    {
        $vehicle = $this->createVehicle('available');
        $completionDate = now()->subDays(3)->toDateString();
        $log = $this->createLog($vehicle, [
            'status' => 'completed',
            'completion_date' => $completionDate,
        ]);

        $this->actingAs($this->createAdministrator(), 'sanctum')
            ->patchJson("/api/maintenance-logs/{$log->id}", ['description' => 'Updated description'])
            ->assertOk();

        $this->assertDatabaseHas('vehicle_maintenance', [
            'id' => $log->id,
            'completion_date' => $completionDate,
        ]);
    }

    private function createAdministrator(): User
    {
        return User::factory()->create(['role' => 'administrator']);
    }

    private function createVehicle(string $status = 'maintenance'): Vehicle
    {
        return Vehicle::create([
            'plate_number' => 'TEST-001',
            'vehicle_model' => 'Test vehicle',
            'vehicle_type' => 'van',
            'status' => $status,
        ]);
    }

    private function createLog(Vehicle $vehicle, array $overrides = []): VehicleMaintenance
    {
        return VehicleMaintenance::create(array_merge([
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'inspection',
            'maintenance_category' => 'preventive',
            'description' => 'Scheduled inspection',
            'maintenance_date' => now()->addDays(5)->toDateString(),
            'completion_date' => null,
            'next_due_date' => now()->addMonths(6)->toDateString(),
            'status' => 'in_progress',
        ], $overrides));
    }
}
