<?php

namespace App\Services;

use InvalidArgumentException;

class HazardScoreService
{
    public function calculate(
        float $diameterMaxM,
        float $velocityKmh,
        float $missDistanceKm
    ): float {
        foreach ([
            $diameterMaxM,
            $velocityKmh,
            $missDistanceKm,
        ] as $value) {
            if (! is_finite($value) || $value < 0) {
                throw new InvalidArgumentException(
                    'Hazard Score parameters must be finite and non-negative.'
                );
            }
        }

        // Нормалізація максимального діаметра
        $diameterScore = min(1.0, $diameterMaxM / 1000);

        // Нормалізація швидкості зближення
        $velocityScore = min(1.0, $velocityKmh / 100000);

        // Оцінка близькості до Землі
        $distanceScore = 1 / (1 + $missDistanceKm / 1000000);

        // Підсумковий індекс небезпеки 0-100
        $hazardScore = 100 * (
            0.30 * $diameterScore +
            0.25 * $velocityScore +
            0.45 * $distanceScore
        );

        return round($hazardScore, 2);
    }
}
