<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->time('old_departure_time')->nullable()->after('new_date');
            $table->time('new_departure_time')->nullable()->after('old_departure_time');
            $table->time('old_estimated_return_time')->nullable()->after('new_departure_time');
            $table->time('new_estimated_return_time')->nullable()->after('old_estimated_return_time');
            $table->string('old_destination')->nullable()->after('new_estimated_return_time');
            $table->string('new_destination')->nullable()->after('old_destination');
            $table->string('old_purpose')->nullable()->after('new_destination');
            $table->string('new_purpose')->nullable()->after('old_purpose');
            $table->integer('old_number_of_passengers')->nullable()->after('new_purpose');
            $table->integer('new_number_of_passengers')->nullable()->after('old_number_of_passengers');
        });
    }

    public function down(): void
    {
        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->dropColumn([
                'old_departure_time', 'new_departure_time',
                'old_estimated_return_time', 'new_estimated_return_time',
                'old_destination', 'new_destination',
                'old_purpose', 'new_purpose',
                'old_number_of_passengers', 'new_number_of_passengers',
            ]);
        });
    }
};
