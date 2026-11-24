<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reschedule_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('reschedule_requests', 'old_trip_type')) {
                $table->string('old_trip_type', 20)->nullable();
            }

            if (! Schema::hasColumn('reschedule_requests', 'new_trip_type')) {
                $table->string('new_trip_type', 20)->nullable();
            }

            if (! Schema::hasColumn('reschedule_requests', 'old_authorized_passengers')) {
                $table->string('old_authorized_passengers', 255)->nullable();
            }

            if (! Schema::hasColumn('reschedule_requests', 'new_authorized_passengers')) {
                $table->string('new_authorized_passengers', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = array_filter([
            'old_trip_type',
            'new_trip_type',
            'old_authorized_passengers',
            'new_authorized_passengers',
        ], fn (string $column) => Schema::hasColumn('reschedule_requests', $column));

        if ($columns !== []) {
            Schema::table('reschedule_requests', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
