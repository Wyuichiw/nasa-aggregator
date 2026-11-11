<?php

namespace App\Services;

use App\Models\Asteroid;
use Illuminate\Support\Facades\DB;

class AsteroidHazardUpdater
{
    public function __construct(
        private readonly HazardScoreService $hazardScoreService
    ) {}

    public function update(array $asteroidIds): void
    {
        if ($asteroidIds === []) {
            return;
        }

        // Завантажуємо всі потрібні астероїди та їхні зближення
        $asteroids = Asteroid::query()
            ->with([
                'closeApproachEvents' => function ($query) {
                    $query->where('orbiting_body', 'Earth')
                        ->orderBy('miss_distance_km')
                        ->orderBy('id');
                },
            ])
            ->whereIn('id', $asteroidIds)
            ->get();

        $scores = [];

        foreach ($asteroids as $asteroid) {
            // Найближче за дистанцією зближення із Землею
            $approach = $asteroid->closeApproachEvents->first();

            $score = null;

            if (
                $asteroid->estimated_diameter_max_m !== null &&
                $approach !== null
            ) {
                $score = $this->hazardScoreService->calculate(
                    (float) $asteroid->estimated_diameter_max_m,
                    (float) $approach->relative_velocity_kmh,
                    (float) $approach->miss_distance_km
                );
            }

            $scores[] = [
                'id' => $asteroid->id,
                'calculated_hazard_index' => $score,
                'updated_at' => now(),
            ];
        }

        if ($scores !== []) {
            // Масово оновлюємо тільки наявні записи
            $cases = [];
            $bindings = [];

            foreach ($scores as $score) {
                $cases[] = 'WHEN ? THEN ?';
                $bindings[] = $score['id'];
                $bindings[] = $score['calculated_hazard_index'];
            }

            $ids = array_column($scores, 'id');
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));

            $sql = 'UPDATE asteroids
                SET calculated_hazard_index = CASE id '
                .implode(' ', $cases).
                ' END,
                updated_at = ?
                WHERE id IN ('.$placeholders.')';

            $bindings[] = now();

            DB::update($sql, array_merge($bindings, $ids));
        }
    }
}
