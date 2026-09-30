<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_travel_reports', function (Blueprint $table) {
            $table->id();
            $table->integer('vehicle_id');
            $table->foreign('vehicle_id')
                ->references('id')
                ->on('vehicles')
                ->restrictOnDelete();
            $table->integer('trip_id')->nullable();
            $table->foreign('trip_id')
                ->references('id')
                ->on('trips')
                ->nullOnDelete();
            $table->date('travel_date_from');
            $table->date('travel_date_to')->nullable();
            $table->string('places_of_travel', 500);
            $table->text('defects_observed')->nullable();
            $table->text('defects_incurred')->nullable();
            $table->text('remarks')->nullable();
            $table->string('drivers', 500)->nullable();
            $table->dateTime('arrival_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'travel_date_from']);
            $table->index('trip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_travel_reports');
    }
};
