<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AsteroidAnalyticsService
{
    public function __construct(
        private readonly HazardScoreService $hazardScoreService
    ) {}

    public function monthlyStatistics(
        int $year,
        int $month
    ): array {
        $this->validateYear($year);

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(
                'Month must be between 1 and 12.'
            );
        }

        $start = CarbonImmutable::create(
            $year, $month, 1, 0, 0, 0, 'UTC'
        );

        $end = $start->addMonth();

        return $this->statisticsForPeriod($start, $end);
    }

    public function yearlyStatistics(int $year): array
    {
        $this->validateYear($year);

        $start = CarbonImmutable::create(
            $year, 1, 1, 0, 0, 0, 'UTC'
        );

        $end = $start->addYear();

        return $this->statisticsForPeriod($start, $end);
    }

    private function validateYear(int $year): void
    {
        if ($year < 1000 || $year > 9998) {
            throw new InvalidArgumentException(
                'Year must be between 1000 and 9998.'
            );
        }
    }

    private function statisticsForPeriod(
        CarbonImmutable $start,
        CarbonImmutable $end
    ): array {
        $result = DB::table('close_approach_events as cae')
            ->join(
                'asteroids as a',
                'a.id',
                '=',
                'cae.asteroid_id'
            )
            ->where('cae.orbiting_body', 'Earth')
            ->where(
                'cae.close_approach_date',
                '>=',
                $start->toDateString()
            )
            ->where(
                'cae.close_approach_date',
                '<',
                $end->toDateString()
            )
            ->selectRaw('
                COUNT(*) AS approaches_count,
                COUNT(DISTINCT a.id) AS asteroids_count,
                COUNT(DISTINCT CASE
                    WHEN a.is_potentially_hazardous = 1
                    THEN a.id
                END) AS hazardous_asteroids_count,
                AVG(cae.relative_velocity_kmh) AS average_velocity_kmh,
                MIN(cae.miss_distance_km) AS minimum_distance_km
            ')
            ->first();

        return [
            'approaches_count' => (int) $result->approaches_count,
            'asteroids_count' => (int) $result->asteroids_count,
            'hazardous_asteroids_count' => (int) $result->hazardous_asteroids_count,
            'average_velocity_kmh' => $result->average_velocity_kmh === null
                    ? null
                    : round((float) $result->average_velocity_kmh, 2),
            'minimum_distance_km' => $result->minimum_distance_km === null
                    ? null
                    : round((float) $result->minimum_distance_km, 2),
        ];
    }

    public function topHazardousAsteroids(
        int $year,
        int $month,
        bool $includeSqlScore = false
    ): array {
        $this->validateYear($year);

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(
                'Month must be between 1 and 12.'
            );
        }

        $start = CarbonImmutable::create(
            $year, $month, 1, 0, 0, 0, 'UTC'
        );

        $end = $start->addMonth();

        // Вибираємо найближче зближення кожного астероїда
        // саме протягом заданого місяця
        $rankedApproaches = DB::table('close_approach_events as cae')
            ->join('asteroids as a', 'a.id', '=', 'cae.asteroid_id')
            ->where('cae.orbiting_body', 'Earth')
            ->where('cae.close_approach_date', '>=', $start->toDateString())
            ->where('cae.close_approach_date', '<', $end->toDateString())
            ->whereNotNull('a.estimated_diameter_max_m')
            ->select([
                'a.id as asteroid_id',
                'a.nasa_reference_id',
                'a.name',
                'a.estimated_diameter_max_m',
                'a.is_potentially_hazardous',
                'cae.close_approach_date',
                'cae.relative_velocity_kmh',
                'cae.miss_distance_km',
            ])
            ->selectRaw('
                        ROW_NUMBER() OVER (
                            PARTITION BY a.id
                            ORDER BY cae.miss_distance_km ASC, cae.id ASC
                        ) AS row_num
                    ');

        // Залишаємо по одному найближчому зближенню
        // для кожного астероїда
        $approaches = DB::query()
            ->fromSub($rankedApproaches, 'ranked')
            ->where('row_num', 1)
            ->select('ranked.*')
            ->selectRaw('
                ROUND(
                    100 * (
                        0.30 * LEAST(1.0, estimated_diameter_max_m / 1000) +
                        0.25 * LEAST(1.0, relative_velocity_kmh / 100000) +
                        0.45 * (
                            1 / (1 + miss_distance_km / 1000000)
                        )
                    ),
                    2
                ) AS monthly_hazard_score
            ')
            ->orderByDesc('monthly_hazard_score')
            ->orderBy('asteroid_id')
            ->limit(10)
            ->get();

        $results = [];

        foreach ($approaches as $approach) {
            $score = $this->hazardScoreService->calculate(
                (float) $approach->estimated_diameter_max_m,
                (float) $approach->relative_velocity_kmh,
                (float) $approach->miss_distance_km
            );

            $results[] = [
                'asteroid_id' => (int) $approach->asteroid_id,
                'nasa_reference_id' => $approach->nasa_reference_id,
                'name' => $approach->name,
                'is_potentially_hazardous' => (bool) $approach->is_potentially_hazardous,
                'close_approach_date' => $approach->close_approach_date,
                'relative_velocity_kmh' => (float) $approach->relative_velocity_kmh,
                'miss_distance_km' => (float) $approach->miss_distance_km,
                'hazard_score' => $score,
            ];

            if ($includeSqlScore) {
                $results[array_key_last($results)]['sql_hazard_score'] =
                    (float) $approach->monthly_hazard_score;
            }
        }

        return $results;
    }
}
