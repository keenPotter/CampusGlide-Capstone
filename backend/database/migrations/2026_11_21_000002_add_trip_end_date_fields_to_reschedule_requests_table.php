<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->date('old_trip_end_date')->nullable()->after('new_date');
            $table->date('new_trip_end_date')->nullable()->after('old_trip_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->dropColumn([
                'old_trip_end_date',
                'new_trip_end_date',
            ]);
        });
    }
};