<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('guard_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId(column: 'vehicle_request_id')->constrained(table: 'vehicle_requests')->cascadeOnDelete();
            $table->foreignId(column: 'guard_id')->constrained(table: 'users')->restrictOnDelete();
            $table->string(column: 'vehicle_used')->nullable();
            $table->date(column: 'actual_departure_date')->nullable();
            $table->time(column: 'actual_departure_time')->nullable();
            $table->date(column: 'actual_return_date')->nullable();
            $table->time(column: 'actual_return_time')->nullable();
            $table->string(column: 'vehicle_condition_departure')->nullable();
            $table->string(column: 'vehicle_condition_return')->nullable();
            $table->string(column: 'remarks', length: 500)->nullable();
            $table->timestamps();

            $table->unique(columns: 'vehicle_request_id');
            $table->index(columns: 'guard_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guard_logs');
    }
};