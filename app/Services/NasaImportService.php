<?php

namespace App\Services;

use App\Jobs\DownloadNasaApodImage;
use App\Models\Asteroid;
use App\Models\CloseApproachEvent;
use App\Models\NasaMedia;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NasaImportService
{
    public function __construct(
        private readonly NasaApiService $nasaApiService,
        private readonly AsteroidHazardUpdater $hazardUpdater
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
                'is_potentially_hazardous' => $asteroid->isPotentiallyHazardous,
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

            // Перераховуємо Hazard Score після збереження зближень
            $this->hazardUpdater->update(
                $asteroidIds->values()->all()
            );
        });

        return count($asteroids);
    }

    public function importApod(string $date): NasaMedia
    {
        $apod = $this->nasaApiService->getApod($date);

        return DB::transaction(function () use ($apod) {
            $media = NasaMedia::query()->firstOrNew([
                'media_date' => $apod->date,
            ]);

            $sourceChanged = $media->exists && (
                $media->source_url !== $apod->sourceUrl ||
                $media->media_type !== $apod->mediaType
            );

            $media->fill([
                'title' => $apod->title,
                'explanation' => $apod->explanation,
                'media_type' => $apod->mediaType,
                'source_url' => $apod->sourceUrl,
                'copyright' => $apod->copyright,
            ]);

            if ($sourceChanged) {
                $media->local_storage_path = null;
            }

            $media->save();

            // Автоматичне визначення тегів за назвою та описом APOD.
            $text = Str::lower(
                $media->title.' '.$media->explanation
            );

            $tagPatterns = [
                'Mars' => '/\b(mars|martian)\b/u',
                'Galaxy' => '/\b(galaxy|galaxies|galactic)\b/u',
                'Telescopes' => '/\b(telescope|telescopes)\b/u',
                'Moon' => '/\b(moon|lunar)\b/u',
                'Nebula' => '/\b(nebula|nebulae)\b/u',
            ];

            $matchedTags = [];

            foreach ($tagPatterns as $tagName => $pattern) {
                if (preg_match($pattern, $text)) {
                    $matchedTags[] = $tagName;
                }
            }

            if ($matchedTags !== []) {
                $tagIds = Tag::query()
                    ->whereIn('name', $matchedTags)
                    ->pluck('id')
                    ->all();

                $media->tags()->syncWithoutDetaching($tagIds);
            }

            if (
                $media->media_type === 'image' &&
                (
                    $media->local_storage_path === null ||
                    ! Storage::disk('public')->exists(
                        $media->local_storage_path
                    )
                )
            ) {
                DownloadNasaApodImage::dispatch($media)->afterCommit();
            }

            return $media;
        });
    }
}
