<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
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
        $users = [];

        foreach ($roles as $role) {
            $users[$role] = User::factory()->create([
                'first_name' => ucfirst($role),
                'last_name' => 'Test',
                'email' => "{$role}@nvsu.edu.ph",
                'role' => $role,
            ]);
        }

        // Sample vehicles (previously only in campusglide_db.sql)
        Vehicle::create(['plate_number' => 'NCR 001', 'vehicle_model' => 'Toyota Hiace', 'vehicle_type' => 'van', 'color' => 'White', 'manufacture_year' => 2019, 'capacity' => 15, 'status' => 'available']);
        Vehicle::create(['plate_number' => 'NCR 002', 'vehicle_model' => 'Toyota Innova', 'vehicle_type' => 'suv', 'color' => 'Silver', 'manufacture_year' => 2020, 'capacity' => 8, 'status' => 'available']);
        Vehicle::create(['plate_number' => 'NCR 003', 'vehicle_model' => 'Mitsubishi L300', 'vehicle_type' => 'van', 'color' => 'White', 'manufacture_year' => 2018, 'capacity' => 12, 'status' => 'available']);

        // The trip scheduler needs a driver record linked to the driver user
        Driver::create([
            'user_id' => $users['driver']->id,
            'license_number' => 'DL-12345-2026',
            'license_expiry_date' => '2030-12-31',
            'contact_number' => '09198765432',
            'is_available' => true,
        ]);

        VehicleRequest::factory(10)->create();
    }
}