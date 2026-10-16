<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('allocations', 'ruv_number')) {
            return;
        }

        Schema::table('allocations', function (Blueprint $table) {
            // Format: YYYY-NNNN (for example 2026-0001). Filled in the first time the form is printed.
            $table->string('ruv_number', 12)->nullable()->unique()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            $table->dropUnique(['ruv_number']);
            $table->dropColumn('ruv_number');
        });
    }
};
