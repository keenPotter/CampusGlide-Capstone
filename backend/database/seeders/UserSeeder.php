<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * IDs: 1-2 administrators | 3 staff | 4-8 faculty (4 is the University President)
 *      9-10 test accounts for the UAT account tests (edit, delete, restore, deactivated sign-in)
 *      11-13 extra administrators for testers (5 administrators in total)
 *      14-17 extra staff for testers (5 staff in total)
 *      18-22 extra faculty for testers. Each one has its own requests (see UatTesterSeeder)
 *      23-27 practice accounts for TC-AD-19 (Delete account), one for each administrator tester
 * The administrators 1-2 are the Chief, Motorpool and the Unit Head-GSU printed on the request form.
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

            // ---------- Extra administrators (one for each administrator tester) ----------
            [11, 'admin.tester1@nvsu.edu.ph',   'Test', 'Admin One',   'Administrator (Tester)', '09171234511', 'administrator', true, null],
            [12, 'admin.tester2@nvsu.edu.ph',   'Test', 'Admin Two',   'Administrator (Tester)', '09171234512', 'administrator', true, null],
            [13, 'admin.tester3@nvsu.edu.ph',   'Test', 'Admin Three', 'Administrator (Tester)', '09171234513', 'administrator', true, null],

            // ---------- Extra staff (one for each staff tester) ----------
            [14, 'staff.tester1@nvsu.edu.ph',   'Test', 'Staff One',   'Motorpool Staff (Tester)', '09171234514', 'staff', true, null],
            [15, 'staff.tester2@nvsu.edu.ph',   'Test', 'Staff Two',   'Motorpool Staff (Tester)', '09171234515', 'staff', true, null],
            [16, 'staff.tester3@nvsu.edu.ph',   'Test', 'Staff Three', 'Motorpool Staff (Tester)', '09171234516', 'staff', true, null],
            [17, 'staff.tester4@nvsu.edu.ph',   'Test', 'Staff Four',  'Motorpool Staff (Tester)', '09171234517', 'staff', true, null],

            // ---------- Extra faculty (one for each faculty tester, each has its own requests) ----------
            [18, 'faculty.tester1@nvsu.edu.ph', 'Test Faculty', 'One',   'Instructor I', '09201234531', 'faculty', true, null],
            [19, 'faculty.tester2@nvsu.edu.ph', 'Test Faculty', 'Two',   'Instructor I', '09201234532', 'faculty', true, null],
            [20, 'faculty.tester3@nvsu.edu.ph', 'Test Faculty', 'Three', 'Instructor I', '09201234533', 'faculty', true, null],
            [21, 'faculty.tester4@nvsu.edu.ph', 'Test Faculty', 'Four',  'Instructor I', '09201234534', 'faculty', true, null],
            [22, 'faculty.tester5@nvsu.edu.ph', 'Test Faculty', 'Five',  'Instructor I', '09201234535', 'faculty', true, null],

            // ---------- Practice accounts for "Delete account" (TC-AD-19). Do not sign in with these. ----------
            [23, 'delete.practice1@nvsu.edu.ph', 'Practice', 'Delete One',   'Instructor I', '09201234541', 'faculty', true, null],
            [24, 'delete.practice2@nvsu.edu.ph', 'Practice', 'Delete Two',   'Instructor I', '09201234542', 'faculty', true, null],
            [25, 'delete.practice3@nvsu.edu.ph', 'Practice', 'Delete Three', 'Instructor I', '09201234543', 'faculty', true, null],
            [26, 'delete.practice4@nvsu.edu.ph', 'Practice', 'Delete Four',  'Instructor I', '09201234544', 'faculty', true, null],
            [27, 'delete.practice5@nvsu.edu.ph', 'Practice', 'Delete Five',  'Instructor I', '09201234545', 'faculty', true, null],
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