<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministratorGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_registration_is_limited_to_two_active_administrators(): void
    {
        $administrator = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);
        User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);

        $this->actingAs($administrator, 'sanctum')
            ->postJson('/api/register', $this->registrationData('third.admin@example.test', User::ROLE_ADMINISTRATOR))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Maximum limit of 2 administrators reached.');

        $this->actingAs($administrator, 'sanctum')
            ->postJson('/api/register', $this->registrationData('staff.member@example.test', User::ROLE_STAFF))
            ->assertCreated();

        $this->assertSame(
            2,
            User::query()->activeAdministrators()->count()
        );
    }

    public function test_admin_promotion_is_limited_to_two_active_administrators(): void
    {
        $administrator = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);
        User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($administrator, 'sanctum')
            ->putJson("/api/users/{$staff->id}", [
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'email' => $staff->email,
                'position' => $staff->position,
                'phone_number' => $staff->phone_number,
                'role' => User::ROLE_ADMINISTRATOR,
            ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Maximum limit of 2 administrators reached.');

        $this->assertSame(User::ROLE_STAFF, $staff->fresh()->role);
    }

    public function test_admin_transfer_creates_replacement_and_deactivates_outgoing_admin_atomically(): void
    {
        $outgoingAdministrator = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);
        User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);
        $token = $outgoingAdministrator->createToken('existing-device')->accessToken;

        $response = $this->actingAs($outgoingAdministrator, 'sanctum')
            ->postJson('/api/admin-transfer', $this->registrationData('replacement.admin@example.test'));

        $response->assertCreated()
            ->assertJsonPath('data.role', User::ROLE_ADMINISTRATOR);

        $this->assertDatabaseHas('users', [
            'id' => $outgoingAdministrator->id,
            'email' => $outgoingAdministrator->email,
            'role' => User::ROLE_ADMINISTRATOR,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'replacement.admin@example.test',
            'role' => User::ROLE_ADMINISTRATOR,
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->assertSame(
            2,
            User::query()->activeAdministrators()->count()
        );
    }

    public function test_deactivation_revokes_tokens_but_retains_user_row(): void
    {
        $administrator = User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]);
        $user = User::factory()->create(['role' => User::ROLE_FACULTY]);
        $token = $user->createToken('existing-device')->accessToken;

        $this->actingAs($administrator, 'sanctum')
            ->deleteJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
            'is_active' => false,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    private function registrationData(string $email, string $role = User::ROLE_ADMINISTRATOR): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'Administrator',
            'email' => $email,
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'role' => $role,
        ];
    }
}
