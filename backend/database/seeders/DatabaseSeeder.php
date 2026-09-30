<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VehicleRequest;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = ['administrator', 'driver', 'faculty', 'guard'];

        foreach ($roles as $role) {
            User::factory()->create([
                'first_name' => ucfirst($role),
                'last_name' => 'Test',
                'email' => "{$role}@nvsu.edu.ph",
                'role' => $role,
            ]);
        }

        VehicleRequest::factory(10)->create();
    }
}
