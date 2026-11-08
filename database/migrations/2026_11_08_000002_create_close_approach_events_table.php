<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('close_approach_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asteroid_id')->constrained('asteroids')->cascadeOnDelete();
            $table->date('close_approach_date');
            $table->dateTime('close_approach_datetime');
            $table->decimal('relative_velocity_kmh', 12, 2);
            $table->decimal('miss_distance_km', 15, 2);
            $table->string('orbiting_body')->default('Earth');
            $table->timestamps();

            $table->index(['close_approach_date', 'miss_distance_km'], 'approaches_date_distance_index');
            // Prevent repeated NASA imports from duplicating the same approach.
            $table->unique(['asteroid_id', 'close_approach_datetime', 'orbiting_body'], 'approaches_unique_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('close_approach_events');
    }
};
