<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_account_creation_is_logged_and_admins_can_filter_logs(): void
    {
        $administrator = User::factory()->create([
            'email' => 'audit.admin@example.test',
            'role' => User::ROLE_ADMINISTRATOR,
        ]);

        $response = $this->actingAs($administrator, 'sanctum')
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.15'])
            ->postJson('/api/register', [
                'first_name' => 'Audit',
                'last_name' => 'Subject',
                'email' => 'audit.subject@example.test',
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
                'role' => User::ROLE_FACULTY,
            ])
            ->assertCreated();

        $targetId = $response->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'CREATE_USER',
            'target_table' => 'users',
            'target_id' => (string) $targetId,
            'ip_address' => '203.0.113.15',
        ]);

        $this->actingAs($administrator, 'sanctum')
            ->postJson('/api/register', [
                'first_name' => 'Audit',
                'last_name' => 'Subject',
                'email' => 'audit.subject@example.test',
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
                'role' => User::ROLE_FACULTY,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('audit_logs', 1);

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/audit-logs?action=CREATE_USER&user_email=audit.admin%40example.test&per_page=5')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'CREATE_USER')
            ->assertJsonPath('data.0.user.email', 'audit.admin@example.test')
            ->assertJsonPath('data.0.target_id', (string) $targetId)
            ->assertJsonPath('data.0.ip_address', '203.0.113.15');
    }

    public function test_successful_login_is_logged_with_the_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'login.audit@example.test',
            'password' => 'correct-password',
            'role' => User::ROLE_FACULTY,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'role' => User::ROLE_FACULTY,
        ], ['X-Client-Session' => '01234567-89ab-cdef'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'LOGIN',
            'target_table' => 'users',
            'target_id' => (string) $user->id,
        ]);
    }

    public function test_logout_is_logged_even_though_it_revokes_the_current_token(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $token = $user->createToken('logout-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'LOGOUT',
            'target_table' => 'users',
            'target_id' => (string) $user->id,
        ]);
    }

    public function test_only_administrators_can_view_logs_and_mutation_routes_do_not_exist(): void
    {
        $administrator = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $faculty = User::factory()->create(['role' => User::ROLE_FACULTY]);

        $this->actingAs($staff, 'sanctum')->getJson('/api/audit-logs')->assertForbidden();
        $this->actingAs($faculty, 'sanctum')->getJson('/api/audit-logs')->assertForbidden();

        $this->actingAs($administrator, 'sanctum')
            ->putJson('/api/audit-logs/1', ['action' => 'ALTERED'])
            ->assertNotFound();

        $this->actingAs($administrator, 'sanctum')
            ->deleteJson('/api/audit-logs/1')
            ->assertNotFound();
    }

    public function test_audit_log_models_reject_updates_and_deletes(): void
    {
        $user = User::factory()->create();

        DB::table('audit_logs')->insert([
            'user_id' => $user->id,
            'action' => 'TEST_ACTION',
            'target_table' => null,
            'target_id' => null,
            'ip_address' => null,
            'created_at' => now(),
        ]);

        $log = AuditLog::query()->firstOrFail();
        $log->action = 'ALTERED';

        try {
            $log->save();
            $this->fail('Audit log updates must be rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Audit log records are append-only.', $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'TEST_ACTION']);

        try {
            $log->delete();
            $this->fail('Audit log deletes must be rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Audit log records are append-only.', $exception->getMessage());
        }
    }
}
