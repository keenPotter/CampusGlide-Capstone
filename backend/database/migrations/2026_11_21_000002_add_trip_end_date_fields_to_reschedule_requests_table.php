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
            $table->string('old_trip_type', 20)->nullable()->after('new_trip_end_date');
            $table->string('new_trip_type', 20)->nullable()->after('old_trip_type');
            $table->string('old_authorized_passengers', 255)->nullable()->after('new_trip_type');
            $table->string('new_authorized_passengers', 255)->nullable()->after('old_authorized_passengers');
        });
    }

    public function down(): void
    {
        Schema::table('reschedule_requests', function (Blueprint $table) {
            $table->dropColumn([
                'old_trip_end_date',
                'new_trip_end_date',
                'old_trip_type',
                'new_trip_type',
                'old_authorized_passengers',
                'new_authorized_passengers',
            ]);
        });
    }
};