<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The allocation and trip pages often look for trips by status and date,
    // and the date change list is filtered by status. These indexes make those lookups faster.
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->index(['trip_status', 'trip_date'], 'idx_trips_status_date');
        });

        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->index('status', 'idx_reschedule_status');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex('idx_trips_status_date');
        });

        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->dropIndex('idx_reschedule_status');
        });
    }
};