<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asteroids', function (Blueprint $table) {
            $table->id();
            $table->string('nasa_reference_id')->unique();
            $table->string('name');
            $table->decimal('estimated_diameter_min_m', 10, 2)->nullable();
            $table->decimal('estimated_diameter_max_m', 10, 2)->nullable();
            $table->boolean('is_potentially_hazardous')->default(false);
            $table->decimal('calculated_hazard_index', 5, 2)->default(0);
            $table->timestamps();

            $table->index(['is_potentially_hazardous', 'calculated_hazard_index'], 'asteroids_hazard_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asteroids');
    }
};
