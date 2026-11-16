<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('trip_status')->default('scheduled')->change();
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        DB::table('trips')
            ->where('trip_status', 'cancelled')
            ->update([
                'trip_status' => 'scheduled',
                'cancellation_reason' => null,
                'cancelled_by' => null,
                'cancelled_at' => null,
            ]);

        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->enum('trip_status', ['scheduled', 'in_progress', 'completed'])
                ->default('scheduled')
                ->change();
        });
    }
};
