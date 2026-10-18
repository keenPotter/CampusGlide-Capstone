<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Process account deletion requests after the full 30-day recovery window.
// Account identity is anonymized instead of deleting the row so historical requests,
// allocations, trips, and audit references remain intact.
Artisan::command('users:process-deletions', function () {
    $users = \App\Models\User::whereNotNull('deletion_requested_at')
        ->where('deletion_requested_at', '<=', now()->subDays(30))->get();
    foreach ($users as $user) {
        if ($user->role === 'administrator') continue;
        $user->tokens()->delete();
        $user->email = 'deleted-user-' . $user->id . '@deleted.invalid';
        $user->first_name = 'Deleted';
        $user->last_name = 'User';
        $user->position = null;
        $user->phone_number = null;
        $user->password = \Illuminate\Support\Str::random(64);
        $user->is_active = false;
        $user->deletion_requested_at = null;
        $user->save();
    }
    $this->info($users->count() . ' expired account deletion request(s) processed. Historical records were preserved.');
})->purpose('Anonymize accounts whose 30-day deletion period has expired');
