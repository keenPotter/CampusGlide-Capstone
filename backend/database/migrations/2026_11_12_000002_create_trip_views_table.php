<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trip_views')) {
            return;
        }

        Schema::create('trip_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->unique(['user_id', 'trip_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_views');
    }
};