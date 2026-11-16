<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_token_without_counting_as_failures(): void
    {
        User::factory()->create([
            'email' => 'person@example.edu',
            'password' => 'correct-password',
            'role' => 'faculty',
        ]);
        $credentials = [
            'email' => 'person@example.edu',
            'password' => 'correct-password',
            'role' => 'faculty',
        ];
        $headers = ['X-Client-Session' => '01234567-89ab-cdef'];

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/login', $credentials, $headers)
                ->assertOk()
                ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);
        }
    }

    public function test_login_failures_return_field_specific_validation_errors(): void
    {
        $user = User::factory()->create([
            'email' => 'person@example.edu',
            'password' => 'correct-password',
            'role' => 'faculty',
        ]);
        $headers = ['X-Client-Session' => '01234567-89ab-cdef'];
        $credentials = [
            'email' => 'person@example.edu',
            'password' => 'incorrect-password',
            'role' => 'faculty',
        ];

        $incorrectPassword = $this->postJson('/api/login', $credentials, $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors', [
                'password' => ['The provided password is incorrect.'],
            ]);

        $user->update(['is_active' => false]);
        $inactiveAccount = $this->postJson('/api/login', [
            ...$credentials,
            'password' => 'correct-password',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors', [
                'email' => ['This account has been deactivated.'],
            ]);

        $user->update(['is_active' => true]);
        $wrongRole = $this->postJson('/api/login', [
            ...$credentials,
            'password' => 'correct-password',
            'role' => 'staff',
        ], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors', [
                'role' => ['The selected account type does not match this account.'],
            ]);

        $this->assertNull($incorrectPassword->json('token'));
        $this->assertNull($inactiveAccount->json('token'));
        $this->assertNull($wrongRole->json('token'));
    }

    public function test_repeated_failed_attempts_are_rate_limited(): void
    {
        $credentials = [
            'email' => 'unknown@example.edu',
            'password' => 'incorrect-password',
            'role' => 'faculty',
        ];
        $headers = ['X-Client-Session' => '01234567-89ab-cdef'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', $credentials, $headers)->assertUnprocessable();
        }

        $this->postJson('/api/login', $credentials, $headers)->assertTooManyRequests();
    }

    public function test_rate_limiter_handles_non_string_email_values(): void
    {
        $this->postJson('/api/login', [
            'email' => ['invalid'],
            'password' => ['invalid'],
            'role' => 'faculty',
        ], ['X-Client-Session' => '01234567-89ab-cdef'])->assertUnprocessable();
    }
}
