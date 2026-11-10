<?php

namespace App\Services;

use App\Models\Asteroid;
use Illuminate\Support\Facades\DB;
use App\Models\CloseApproachEvent;

class NasaImportService
{
    public function __construct(
        private readonly NasaApiService $nasaApiService
    ) {}

    public function importAsteroids(
        string $startDate,
        string $endDate
    ): int {
        $items = $this->nasaApiService->getAsteroids(
            $startDate,
            $endDate
        );

        $asteroids = [];

        foreach ($items as $item) {
            $asteroid = $item['asteroid'];

            $asteroids[$asteroid->nasaReferenceId] = [
                'nasa_reference_id' => $asteroid->nasaReferenceId,
                'name' => $asteroid->name,
                'estimated_diameter_min_m' => $asteroid->diameterMin,
                'estimated_diameter_max_m' => $asteroid->diameterMax,
                'is_potentially_hazardous' =>
                    $asteroid->isPotentiallyHazardous,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($asteroids === []) {
            return 0;
        }

        DB::transaction(function () use ($asteroids, $items) {
            Asteroid::upsert(
                array_values($asteroids),
                ['nasa_reference_id'],
                [
                    'name',
                    'estimated_diameter_min_m',
                    'estimated_diameter_max_m',
                    'is_potentially_hazardous',
                    'updated_at',
                ]
            );

            $asteroidIds = Asteroid::query()
                ->whereIn('nasa_reference_id', array_keys($asteroids))
                ->pluck('id', 'nasa_reference_id');

            $approaches = [];

            foreach ($items as $item) {
                $asteroid = $item['asteroid'];
                $approach = $item['approach'];

                $asteroidId = $asteroidIds[$asteroid->nasaReferenceId];

                $approaches[] = [
                    'asteroid_id' => $asteroidId,
                    'close_approach_date' => $approach->date,
                    'close_approach_datetime' => $approach->datetime,
                    'relative_velocity_kmh' => $approach->velocityKmh,
                    'miss_distance_km' => $approach->distanceKm,
                    'orbiting_body' => $approach->orbitingBody,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            CloseApproachEvent::upsert(
                $approaches,
                [
                    'asteroid_id',
                    'close_approach_datetime',
                    'orbiting_body',
                ],
                [
                    'close_approach_date',
                    'relative_velocity_kmh',
                    'miss_distance_km',
                    'updated_at',
                ]
            );
        });

        return count($asteroids);
    }
}
