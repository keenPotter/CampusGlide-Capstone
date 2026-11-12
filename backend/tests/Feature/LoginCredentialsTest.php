<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_email_error_is_attached_only_to_the_email_field(): void
    {
        $this->postJson('/api/login', [
            'email' => 'unknown@nvsu.edu.ph',
            'password' => 'some-password',
            'role' => 'faculty',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors', [
                'email' => ['The provided email address is incorrect.'],
            ]);
    }

    public function test_wrong_password_error_is_attached_only_to_the_password_field(): void
    {
        User::factory()->create([
            'email' => 'user@nvsu.edu.ph',
            'password' => 'correct-password',
            'role' => 'faculty',
        ]);

        $this->postJson('/api/login', [
            'email' => 'user@nvsu.edu.ph',
            'password' => 'wrong-password',
            'role' => 'faculty',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors', [
                'password' => ['The provided password is incorrect.'],
            ]);
    }
}
