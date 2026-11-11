<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Дозволяємо NULL замість автоматичного значення 0
        Schema::table('asteroids', function (Blueprint $table) {
            $table->decimal('calculated_hazard_index', 5, 2)
                ->nullable()
                ->default(null)
                ->change();
        });

        // Старі нулі ще не були розраховані алгоритмом
        DB::table('asteroids')
            ->where('calculated_hazard_index', 0)
            ->update(['calculated_hazard_index' => null]);
    }

    public function down(): void
    {
        // Повертаємо NULL до початкового значення 0
        DB::table('asteroids')
            ->whereNull('calculated_hazard_index')
            ->update(['calculated_hazard_index' => 0]);

        // Відновлюємо початкову структуру поля
        Schema::table('asteroids', function (Blueprint $table) {
            $table->decimal('calculated_hazard_index', 5, 2)
                ->default(0)
                ->nullable(false)
                ->change();
        });
    }
};
