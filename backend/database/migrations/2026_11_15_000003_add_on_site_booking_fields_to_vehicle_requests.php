<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_requests', function (Blueprint $table) {
            $table->foreignId('requester_id')->nullable()->change();
            $table->string('booking_source', 20)->default('online');
            $table->string('requester_name')->nullable();
            $table->string('requester_department')->nullable();
            $table->string('requester_contact', 50)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('vehicle_requests')->whereNull('requester_id')->exists()) {
            throw new RuntimeException('Cannot remove on-site request fields while requests have no account requester.');
        }

        Schema::table('vehicle_requests', function (Blueprint $table) {
            $table->dropColumn([
                'booking_source',
                'requester_name',
                'requester_department',
                'requester_contact',
            ]);
            $table->foreignId('requester_id')->nullable(false)->change();
        });
    }
};
