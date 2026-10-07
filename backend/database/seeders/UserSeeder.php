<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * IDs: 1-2 administrator | 9-15 faculty
 * (IDs 3-8 are left unused on purpose so the vehicle request seeder keeps working.)
 * All passwords = "password"
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $ts = '2026-09-01 08:00:00';

        $columns = [
            'id', 'email', 'password', 'first_name', 'last_name', 'position',
            'phone_number', 'role', 'is_active', 'created_at', 'updated_at',
        ];

        $users = [
            [1,  'maria.santos@nvsu.edu.ph',        'Maria',      'Santos',     'Motor Pool Head',        '09171234501', 'administrator', true],
            [2,  'roberto.delacruz@nvsu.edu.ph',    'Roberto',    'Dela Cruz',  'Administrative Officer', '09171234502', 'administrator', true],
            [9,  'elena.garcia@nvsu.edu.ph',        'Elena',      'Garcia',     'Professor',              '09201234509', 'faculty',       true],
            [10, 'ricardo.torres@nvsu.edu.ph',      'Ricardo',    'Torres',     'Associate Professor',    '09201234510', 'faculty',       true],
            [11, 'luzviminda.castillo@nvsu.edu.ph', 'Luzviminda', 'Castillo',   'Assistant Professor',    '09201234511', 'faculty',       true],
            [12, 'dennis.manalo@nvsu.edu.ph',       'Dennis',     'Manalo',     'Instructor',             '09201234512', 'faculty',       true],
            [13, 'grace.pascual@nvsu.edu.ph',       'Grace',      'Pascual',    'Department Chair',       '09201234513', 'faculty',       true],
            [14, 'joel.navarro@nvsu.edu.ph',        'Joel',       'Navarro',    'Instructor',             '09201234514', 'faculty',       true],
            [15, 'hilda.soriano@nvsu.edu.ph',       'Hilda',      'Soriano',    'Professor',              null,          'faculty',       false],
        ];

        $rows = array_map(
            fn (array $u) => array_combine($columns, [
                $u[0], $u[1], $password, $u[2], $u[3], $u[4], $u[5], $u[6], $u[7], $ts, $ts,
            ]),
            $users
        );

        DB::table('users')->insert($rows);
    }
}