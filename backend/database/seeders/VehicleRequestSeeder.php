<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Requesters: 4 = faculty1 (University President, exclusive trips only)
 *             5-8 = faculty2 to faculty5 (inclusive trips only)
 * Every faculty account has at least 5 requests, with a mix of statuses.
 * Dates are counted from the day the seeders are run (see SeedDates).
 *
 * Approved, trip completed .......... 1, 2, 3, 4, 27
 * Approved, trip in progress ........ 5, 6      (multi-day, end 7 days from today)
 * Approved, trip scheduled .......... 7, 8, 9, 13, 26, 28, 29, 30, 31, 32
 *   26  starts today (multi-day), so its vehicle and driver show "Scheduled today"
 *   13  its vehicle has maintenance on the trip date (needs reassignment)
 *   8, 28, 29, 30 have a pending date change request; 9 and 13 have a decided one
 * Pending ........................... 10, 11, 12, 14, 15, 16, 17, 33, 34, 35
 *   11  was edited after approval, so it went back to pending
 *   17  same date as trip 8, so its vehicle and driver are already booked (conflict)
 *   12  its dates are after driver 3's license expires
 * Disapproved ....................... 18 to 25
 *
 * Inclusive = inside Nueva Vizcaya. Exclusive = outside Nueva Vizcaya (requested by the president).
 */
class VehicleRequestSeeder extends Seeder
{
    private const MAIN = 'NVSU Main Campus, Bayombong, Nueva Vizcaya';
    private const CAPITOL = 'Provincial Capitol, Bayombong, Nueva Vizcaya';
    private const CONGRESSMAN = 'Office of the Congressman, Bayombong, Nueva Vizcaya';

    private const MAINTENANCE = 'The vehicle needed for this trip is under maintenance on the requested date.';
    private const NO_AVAILABLE = 'No available driver or vehicle on the requested date.';
    private const NO_DATE = 'The requested date is already fully booked. Please choose another date.';
    private const NO_VEHICLE = 'No available vehicle for this group on the requested date. The only van that can carry 13 passengers is already booked.';

    public function run(): void
    {
        $rows = [
            // ---------- Approved: completed trips ----------
            $this->row(1,  5, -29, [-24, -24], 'inclusive', ['08:00:00', '16:00:00'], self::MAIN, 'Faculty general assembly and department meeting', 'Marife Aliaga and 3 faculty members', 4, 'approved', -27, 1),
            $this->row(2,  4, -25, [-19, -18], 'exclusive', ['05:00:00', '20:00:00'], 'Office of the Congressman, House of Representatives, Quezon City, Metro Manila', 'Meeting with the congressman on the university budget and infrastructure projects', 'Wilfredo Dumale Jr. and 2 university officials', 3, 'approved', -23, 2),
            $this->row(3,  6, -17, [-12, -12], 'inclusive', ['09:00:00', '15:00:00'], self::CAPITOL, 'Coordination meeting with provincial officials on extension programs', 'Krizsha Ellaine Salinas and 2 faculty members', 3, 'approved', -15, 1),
            $this->row(4,  7, -13, [-8, -8],   'inclusive', ['08:30:00', '17:00:00'], 'Municipal Hall, Solano, Nueva Vizcaya', 'Meeting with municipal officials on community partnership', 'Dennis Directo and 5 faculty members', 6, 'approved', -11, 2),

            // ---------- Approved: trips in progress (they stay in progress for the whole testing week) ----------
            $this->row(5,  6, -6,  [-1, 7],    'inclusive', ['07:30:00', '16:30:00'], 'Partner municipalities of Nueva Vizcaya', 'Week-long monitoring of students on off-campus practicum', 'Krizsha Ellaine Salinas and 3 faculty members', 4, 'approved', -4, 1),
            $this->row(6,  7, -5,  [-1, 7],    'inclusive', ['08:00:00', '15:00:00'], 'Partner high schools in Bayombong, Solano and Bambang, Nueva Vizcaya', 'Week-long orientation and school visits for the senior high school partnership', 'Dennis Directo and 2 faculty members', 3, 'approved', -4, 2),

            // ---------- Approved: trips scheduled ----------
            $this->row(7,  4, -6,  [10, 11],   'exclusive', ['05:00:00', '20:00:00'], 'Department of Budget and Management, Manila', 'Meeting with DBM officials on the proposed university budget', 'Wilfredo Dumale Jr. and 2 university officials', 3, 'approved', -4, 1),
            $this->row(8,  8, -7,  [9, 9],     'inclusive', ['09:00:00', '16:00:00'], self::MAIN, 'Academic council meeting of all campus faculty', 'Gwen Kyle Gagasa and 11 faculty members', 12, 'approved', -4, 2),
            $this->row(9,  7, -9,  [12, 12],   'inclusive', ['08:00:00', '17:00:00'], self::CAPITOL, 'Presentation of the extension program to the provincial board', 'Dennis Directo and 3 faculty members', 4, 'approved', -7, 1),

            // ---------- Pending ----------
            $this->row(10, 5, -3,  [2, 2],     'inclusive', ['08:00:00', '16:00:00'], 'Municipal Hall, Bambang, Nueva Vizcaya', 'Meeting with the municipal planning office on the campus expansion project', 'Marife Aliaga and 2 faculty members', 3, 'pending'),
            $this->row(11, 6, -6,  [5, 5],     'inclusive', ['09:00:00', '15:30:00'], 'Provincial Health Office, Bayombong, Nueva Vizcaya', 'Meeting with the health office on the nursing students community program', 'Krizsha Ellaine Salinas and 4 faculty members', 5, 'pending', -2, null, null, 'The provincial health office moved the meeting to a later date.'),
            $this->row(12, 4, -3,  [20, 21],   'exclusive', ['05:00:00', '19:00:00'], 'CHED Central Office, Quezon City, Metro Manila', 'Meeting with CHED officials on program accreditation', 'Wilfredo Dumale Jr. and 2 university officials', 3, 'pending'),

            // ---------- Approved: needs reassignment (vehicle 1 has tire service on this date) ----------
            $this->row(13, 5, -9,  [14, 14],   'inclusive', ['08:00:00', '17:00:00'], self::MAIN, 'Department heads meeting on the second semester schedule', 'Marife Aliaga and 7 department heads', 8, 'approved', -7, 2),

            // ---------- Pending ----------
            $this->row(14, 8, -2,  [4, 4],     'inclusive', ['08:00:00', '16:00:00'], self::CAPITOL, 'Meeting with the provincial planning office on a research partnership', 'Gwen Kyle Gagasa and 3 faculty members', 4, 'pending'),
            $this->row(15, 4, -2,  [24, 24],   'exclusive', ['04:00:00', '23:00:00'], 'Ninoy Aquino International Airport (NAIA) Terminal 3, Pasay City, Metro Manila', 'Fetch the University President at the airport', 'Wilfredo Dumale Jr.', 1, 'pending'),
            $this->row(16, 7, -2,  [3, 3],     'inclusive', ['09:00:00', '15:00:00'], 'DTI Provincial Office, Bayombong, Nueva Vizcaya', 'Meeting with DTI officials on the entrepreneurship seminar', 'Dennis Directo and 2 faculty members', 3, 'pending'),
            // Same date as request 8: vehicle 1 and driver 3 are already booked.
            $this->row(17, 6, -4,  [9, 9],     'inclusive', ['08:30:00', '16:30:00'], 'Municipal Hall, Solano, Nueva Vizcaya', 'Meeting with local officials on the barangay IT training program', 'Krizsha Ellaine Salinas and 9 faculty members', 10, 'pending'),

            // ---------- Disapproved ----------
            $this->row(18, 8, -52, [-46, -46], 'inclusive', ['08:00:00', '16:00:00'], self::CAPITOL, 'Meeting with provincial officials on the IT skills program', 'Gwen Kyle Gagasa and 2 faculty members', 3, 'disapproved', -49, 1, self::MAINTENANCE),
            $this->row(19, 5, -45, [-39, -39], 'inclusive', ['09:00:00', '17:00:00'], self::MAIN, 'Faculty planning meeting on the research agenda', 'Marife Aliaga and 9 faculty members', 10, 'disapproved', -42, 2, self::NO_DATE),
            $this->row(20, 4, -40, [-32, -31], 'exclusive', ['05:00:00', '19:00:00'], 'DOST Central Office, Bicutan, Taguig City, Metro Manila', 'Meeting with DOST officials on research funding', 'Wilfredo Dumale Jr. and 2 university officials', 3, 'disapproved', -38, 1, self::NO_AVAILABLE),
            $this->row(21, 6, -34, [-28, -28], 'inclusive', ['08:00:00', '16:00:00'], 'Municipal Hall, Aritao, Nueva Vizcaya', 'Meeting with municipal officials on the community outreach program', 'Krizsha Ellaine Salinas and 3 faculty members', 4, 'disapproved', -31, 2, self::MAINTENANCE),
            $this->row(22, 8, -30, [-23, -23], 'inclusive', ['07:30:00', '15:30:00'], self::CAPITOL, 'Meeting with the provincial agriculture office on the farm technology program', 'Gwen Kyle Gagasa and 2 faculty members', 3, 'disapproved', -27, 1, self::NO_DATE),
            $this->row(23, 8, -22, [-16, -16], 'inclusive', ['09:00:00', '16:00:00'], self::CONGRESSMAN, 'Meeting with congressman staff on the scholarship program', 'Gwen Kyle Gagasa and 3 faculty members', 4, 'disapproved', -19, 2, self::NO_DATE),
            $this->row(24, 7, -20, [-13, -13], 'inclusive', ['08:00:00', '16:00:00'], self::MAIN, 'Faculty training workshop planning', 'Dennis Directo and 12 faculty members', 13, 'disapproved', -17, 1, self::NO_VEHICLE),
            $this->row(25, 5, -15, [-9, -9],   'inclusive', ['08:00:00', '17:00:00'], self::MAIN, 'Faculty meeting on curriculum review', 'Marife Aliaga and 4 faculty members', 5, 'disapproved', -12, 2, self::MAINTENANCE),

            // ---------- Approved: starts today (multi-day), more scheduled trips ----------
            $this->row(26, 4, -5,  [0, 7],     'exclusive', ['05:00:00', '17:00:00'], 'Partner universities in Metro Manila and Baguio City', 'Week-long benchmarking visit to partner universities on academic programs', 'Wilfredo Dumale Jr. and 2 university officials', 3, 'approved', -3, 1),
            $this->row(27, 8, -10, [-5, -5],   'inclusive', ['08:00:00', '15:00:00'], self::CONGRESSMAN, 'Meeting with the congressman and staff on the scholarship program', 'Gwen Kyle Gagasa and 3 faculty members', 4, 'approved', -8, 2),
            $this->row(28, 5, -6,  [15, 15],   'inclusive', ['09:00:00', '16:00:00'], 'DepEd Schools Division Office, Bayombong, Nueva Vizcaya', 'Meeting with DepEd officials on the senior high school partnership', 'Marife Aliaga and 2 faculty members', 3, 'approved', -3, 1),
            $this->row(29, 6, -6,  [16, 16],   'inclusive', ['08:00:00', '17:00:00'], 'Municipal Hall, Bayombong, Nueva Vizcaya', 'Meeting with the municipal mayor on the community extension agreement', 'Krizsha Ellaine Salinas and 3 faculty members', 4, 'approved', -3, 2),
            $this->row(30, 7, -5,  [17, 17],   'inclusive', ['08:00:00', '16:00:00'], 'Nueva Vizcaya Provincial Hospital, Bayombong, Nueva Vizcaya', 'Coordination with hospital officials on nursing student affiliation', 'Dennis Directo and 4 faculty members', 5, 'approved', -2, 1),
            $this->row(31, 6, -4,  [18, 18],   'inclusive', ['09:00:00', '15:00:00'], 'Provincial Agriculture Office, Bayombong, Nueva Vizcaya', 'Meeting on the farm technology demonstration project', 'Krizsha Ellaine Salinas and 2 faculty members', 3, 'approved', -2, 2),
            $this->row(32, 8, -4,  [19, 19],   'inclusive', ['08:30:00', '16:30:00'], self::MAIN, 'Research colloquium and faculty presentation', 'Gwen Kyle Gagasa and 9 faculty members', 10, 'approved', -1, 1),

            // ---------- Pending (extra requests so every tester has one to approve or disapprove) ----------
            $this->row(33, 8, -1,  [6, 6],     'inclusive', ['08:00:00', '16:00:00'], 'Municipal Hall, Dupax del Sur, Nueva Vizcaya', 'Meeting with municipal officials on the youth livelihood training', 'Gwen Kyle Gagasa and 3 faculty members', 4, 'pending'),
            $this->row(34, 5, -1,  [8, 8],     'inclusive', ['09:00:00', '15:00:00'], self::CAPITOL, 'Courtesy call and presentation of the new research center', 'Marife Aliaga and 2 faculty members', 3, 'pending'),
            $this->row(35, 7, -1,  [13, 13],   'inclusive', ['08:00:00', '16:00:00'], 'Municipal Hall, Kayapa, Nueva Vizcaya', 'Meeting on the community-based tourism study', 'Dennis Directo and 2 faculty members', 3, 'pending'),
        ];

        DB::table('vehicle_requests')->insert($rows);
    }

    /**
     * @param  int  $sent  days from today when the request was sent (negative = in the past)
     * @param  array  $days  [first day, last day] counted from today
     * @param  array  $times  [departure time, estimated return time]
     * @param  int|null  $decided  days from today when the request was decided (or edited)
     */
    private function row(
        int $id,
        int $requester,
        int $sent,
        array $days,
        string $type,
        array $times,
        string $destination,
        string $purpose,
        string $passengers,
        int $count,
        string $status,
        ?int $decided = null,
        ?int $approver = null,
        ?string $reason = null,
        ?string $editReason = null,
    ): array {
        $sentAt = SeedDates::at($sent, sprintf('%02d:%02d:00', 8 + $id % 8, ($id * 7) % 60));
        $decidedAt = $decided === null ? null : SeedDates::at($decided, '10:00:00');

        return [
            'id' => $id,
            'requester_id' => $requester,
            'request_date' => $sentAt,
            'trip_date' => SeedDates::date($days[0]),
            'trip_end_date' => SeedDates::date($days[1]),
            'trip_type' => $type,
            'departure_time' => $times[0],
            'destination' => $destination,
            'purpose' => $purpose,
            'estimated_return_time' => $times[1],
            'passengers' => $passengers,
            'number_of_passengers' => $count,
            'status' => $status,
            'disapproval_reason' => $reason,
            'edit_reason' => $editReason,
            'approved_by' => $approver,
            'approved_date' => $status === 'approved' ? $decidedAt : null,
            'created_at' => $sentAt,
            'updated_at' => $decidedAt ?? $sentAt,
        ];
    }
}