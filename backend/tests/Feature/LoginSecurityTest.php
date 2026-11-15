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

    public function test_login_failures_do_not_reveal_account_status_or_role(): void
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
            ->assertUnprocessable();

        $user->update(['is_active' => false]);
        $inactiveAccount = $this->postJson('/api/login', [
            ...$credentials,
            'password' => 'correct-password',
        ], $headers)->assertUnprocessable();

        $user->update(['is_active' => true]);
        $wrongRole = $this->postJson('/api/login', [
            ...$credentials,
            'password' => 'correct-password',
            'role' => 'staff',
        ], $headers)->assertUnprocessable();

        $this->assertSame(
            ['The provided credentials are incorrect.'],
            $incorrectPassword->json('errors.email'),
        );
        $this->assertSame($incorrectPassword->json('errors'), $inactiveAccount->json('errors'));
        $this->assertSame($incorrectPassword->json('errors'), $wrongRole->json('errors'));
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
