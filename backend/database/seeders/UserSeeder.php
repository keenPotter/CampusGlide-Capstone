<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * IDs: 1-2 administrators | 3 staff | 4-8 faculty (4 is the University President)
 *      9-10 test accounts for the UAT account tests (edit, delete, restore, deactivated sign-in)
 * The administrators are the Chief, Motorpool and the Unit Head-GSU printed on the request form.
 * All passwords = "password"
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $ts = SeedDates::at(-55, '08:00:00');

        $columns = [
            'id', 'email', 'password', 'first_name', 'last_name', 'position',
            'phone_number', 'role', 'is_active', 'deletion_requested_at', 'created_at', 'updated_at',
        ];

        // [id, email, first name, last name, position, phone, role, active, days since deletion was requested]
        $users = [
            [1,  'tony.basatan@nvsu.edu.ph',        'Tony',     'Basatan',      'Chief, Motorpool',     '09171234501', 'administrator', true,  null],
            [2,  'jayson.pimentel@nvsu.edu.ph',     'Jayson',   'Pimentel',     'Unit Head-GSU',        '09171234502', 'administrator', true,  null],
            [3,  'reonel.makil@nvsu.edu.ph',        'Reonel',   'Makil',        'Motorpool Staff',      '09171234503', 'staff',         true,  null],
            [4,  'wilfredo.dumale@nvsu.edu.ph',     'Wilfredo', 'Dumale Jr.',   'University President', '09201234510', 'faculty',      true,  null],
            [5,  'marife.aliaga@nvsu.edu.ph',       'Marife',   'Aliaga',       'Professor',            '09201234503', 'faculty',       true,  null],
            [6,  'krizsha.salinas@nvsu.edu.ph',     'Krizsha Ellaine',      'Salinas',   'Instructor',  '09201234505', 'faculty',       true,  null],
            [7,  'dennis.directo@nvsu.edu.ph',      'Dennis',   'Directo',      'Professor',            '09201234506', 'faculty',       true,  null],
            [8,  'gwen.gagasa@nvsu.edu.ph',         'Gwen Kyle','Gagasa',       'Instructor',           '09201234508', 'faculty',       true,  null],
            // Test accounts. Use these to try Edit and Delete without touching the main accounts.
            [9,  'testuser1@nvsu.edu.ph', 'Test',    'User One', 'Instructor I',        '09201234521', 'faculty',       true,  null],
            // Already scheduled for deletion 5 days ago (disabled). Use it for Cancel deletion and the deactivated sign-in test.
            [10, 'testuser2@nvsu.edu.ph', 'Test',    'User Two', 'Instructor I',        '09201234522', 'faculty',       false, -5],
        ];

        $rows = array_map(
            fn (array $u) => array_combine($columns, [
                $u[0], $u[1], $password, $u[2], $u[3], $u[4], $u[5], $u[6], $u[7],
                $u[8] === null ? null : SeedDates::at($u[8], '10:00:00'),
                $ts, $ts,
            ]),
            $users
        );

        DB::table('users')->insert($rows);
    }
}