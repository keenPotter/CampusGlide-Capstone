<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * IDs: 1-2 administrators | 3 staff | 4-8 faculty (4 is the University President)
 * The administrators are the Chief, Motorpool and the Unit Head-GSU printed on the request form.
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
            [1, 'admin1@nvsu.edu.ph',   'Tony',            'Basatan',    'Chief, Motorpool',    '09171234501', 'administrator', true],
            [2, 'admin2@nvsu.edu.ph',   'Jayson',          'Pimentel',   'Unit Head-GSU',       '09171234502', 'administrator', true],
            [3, 'staff1@nvsu.edu.ph',   'Reonel',          'Makil',      'Motorpool Staff',     '09171234502', 'staff',         true],
            [4, 'faculty1@nvsu.edu.ph', 'Faculty',         'One',       'University President', '09201234510', 'faculty',       true],
            [5, 'faculty2@nvsu.edu.ph', 'Faculty',         'Two',        'Professor',           '09201234503', 'faculty',       true],
            [6, 'faculty3@nvsu.edu.ph', 'Faculty',         'Three',      'Associate Professor', '09201234505', 'faculty',       true],
            [7, 'faculty4@nvsu.edu.ph', 'Faculty',         'Four',       'Instructor',          '09201234506', 'faculty',       true],
            [8, 'faculty5@nvsu.edu.ph', 'Faculty',         'Five',       'Instructor',          '09201234508', 'faculty',       true],
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