<?php

namespace Database\Seeders;

use Carbon\Carbon;

/**
 * Dates for the seeders, counted from the day the seeders are run.
 * date(0) is today, date(-5) is five days ago, date(7) is seven days from now.
 * This keeps trips scheduled, in progress or completed as intended during the whole testing week.
 */
class SeedDates
{
    public static function date(int $days): string
    {
        return Carbon::today()->addDays($days)->toDateString();
    }

    public static function at(int $days, string $time): string
    {
        return self::date($days) . ' ' . $time;
    }
}