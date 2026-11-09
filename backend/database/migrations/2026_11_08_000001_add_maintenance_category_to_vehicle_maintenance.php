<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_maintenance', function (Blueprint $table) {
            $table->string('maintenance_category', 20)->default('preventive')->after('maintenance_type');
            $table->index(['status', 'maintenance_date'], 'idx_maintenance_status_date');
        });

        // Existing repair records are corrective; other existing work defaults to preventive.
        DB::table('vehicle_maintenance')
            ->where('maintenance_type', 'repair')
            ->update(['maintenance_category' => 'corrective']);
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenance', function (Blueprint $table) {
            $table->dropIndex('idx_maintenance_status_date');
            $table->dropColumn('maintenance_category');
        });
    }
};
