<?php

namespace App\Services;

use App\DTO\AsteroidData;
use App\DTO\CloseApproachData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use App\DTO\ApodData;
use App\DTO\AsteroidDetailsData;

class NasaApiService
{
    public function getAsteroids(
        string $startDate,
        string $endDate
    ): array {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $endDate);

        if (
            !$start ||
            !$end ||
            $start->format('Y-m-d') !== $startDate ||
            $end->format('Y-m-d') !== $endDate ||
            $start->greaterThan($end) ||
            $start->diffInDays($end) > 6
        ) {
            throw new InvalidArgumentException(
                'Date range must contain 1 to 7 days.'
            );
        }

        $response = Http::timeout(20)->get(
            rtrim(config('services.nasa.base_url'), '/')
            . '/neo/rest/v1/feed',
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'api_key' => config('services.nasa.key'),
            ]
        );

        if ($response->status() === 429) {
            throw new RuntimeException(
                'NASA API rate limit exceeded.'
            );
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                'NASA API request failed. HTTP '
                . $response->status()
            );
        }

        $objects = $response->json('near_earth_objects');

        if (!is_array($objects)) {
            throw new RuntimeException(
                'Invalid NASA NeoWs response.'
            );
        }

        $result = [];

        foreach ($objects as $date => $asteroids) {
            if (!is_array($asteroids)) {
                throw new RuntimeException(
                    'Invalid NASA asteroid list.'
                );
            }

            foreach ($asteroids as $asteroid) {
                if (!is_array($asteroid)) {
                    throw new RuntimeException(
                        'Invalid NASA asteroid entry.'
                    );
                }

                $asteroidData = AsteroidData::fromArray($asteroid);

                if (!array_key_exists('close_approach_data', $asteroid)) {
                    throw new RuntimeException(
                        'Missing NASA close approach data.'
                    );
                }

                $approaches = $asteroid['close_approach_data'];

                if (!is_array($approaches) || !array_is_list($approaches)) {
                    throw new RuntimeException(
                        'Invalid NASA close approach list.'
                    );
                }

                foreach ($approaches as $approach) {
                    if (!is_array($approach)) {
                        throw new RuntimeException(
                            'Invalid NASA close approach entry.'
                        );
                    }

                    $result[] = [
                        'asteroid' => $asteroidData,
                        'approach' => CloseApproachData::fromArray($approach),
                    ];
                }
            }
        }

        return $result;
    }

    public function getAsteroidDetails(string $asteroidId): AsteroidData
    {
        return AsteroidData::fromArray(
            $this->fetchAsteroidDetails($asteroidId)
        );
    }

    public function getLatestApod(): ApodData {
        $response = Http::timeout(20)->get(
            'https://science.nasa.gov/wp-json/wp/v2/apod-basic',
            [
                'per_page' => 1,
            ]
        );

        if ($response->status() === 429) {
            throw new RuntimeException(
                'NASA APOD API rate limit exceeded.'
            );
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                'NASA APOD API request failed. HTTP '
                . $response->status()
            );
        }

        $data = $response->json();

        if (
            !is_array($data) ||
            !isset($data[0]) ||
            !is_array($data[0])
        ) {
            throw new RuntimeException(
                'Invalid NASA APOD response.'
            );
        }

        return ApodData::fromArray($data[0]);
    }

    public function getFullAsteroidDetails(string $asteroidId): AsteroidDetailsData
    {
        return AsteroidDetailsData::fromArray(
            $this->fetchAsteroidDetails($asteroidId)
        );
    }

    private function fetchAsteroidDetails(string $asteroidId): array
    {
        if ($asteroidId === '' || !ctype_digit($asteroidId)) {
            throw new InvalidArgumentException(
                'Invalid NASA asteroid ID.'
            );
        }

        $response = Http::timeout(20)->get(
            rtrim(config('services.nasa.base_url'), '/')
            . '/neo/rest/v1/neo/' . $asteroidId,
            [
                'api_key' => config('services.nasa.key'),
            ]
        );

        if ($response->status() === 429) {
            throw new RuntimeException(
                'NASA API rate limit exceeded.'
            );
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                'NASA API request failed. HTTP '
                . $response->status()
            );
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException(
                'Invalid NASA asteroid details response.'
            );
        }

        return $data;
    }
}
