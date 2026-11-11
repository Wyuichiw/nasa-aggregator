<?php

namespace Tests\Feature;

use App\DTO\AsteroidData;
use App\DTO\CloseApproachData;
use App\Models\Asteroid;
use App\Models\CloseApproachEvent;
use App\Services\AsteroidHazardUpdater;
use App\Services\NasaApiService;
use App\Services\NasaImportService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class NasaImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_asteroids_and_approaches(): void
    {
        $asteroid = new AsteroidData(
            nasaReferenceId: '2307493',
            name: '307493 (2002 XP90)',
            diameterMin: 513.52,
            diameterMax: 1148.26,
            isPotentiallyHazardous: false,
        );

        $approach = new CloseApproachData(
            date: '2026-11-01',
            datetime: '2026-11-01 00:20:00',
            velocityKmh: 57938.91,
            distanceKm: 45865643.87,
            orbitingBody: 'Earth',
        );

        $nasaApi = Mockery::mock(NasaApiService::class);

        $nasaApi->shouldReceive('getAsteroids')
            ->once()
            ->with('2026-11-01', '2026-11-01')
            ->andReturn([
                [
                    'asteroid' => $asteroid,
                    'approach' => $approach,
                ],
            ]);

        $this->app->instance(NasaApiService::class, $nasaApi);

        $result = app(NasaImportService::class)->importAsteroids(
            '2026-11-01',
            '2026-11-01'
        );

        $this->assertSame(1, $result);

        $this->assertDatabaseHas('asteroids', [
            'nasa_reference_id' => '2307493',
            'name' => '307493 (2002 XP90)',
        ]);

        $this->assertDatabaseHas('close_approach_events', [
            'close_approach_date' => '2026-11-01',
            'orbiting_body' => 'Earth',
        ]);

        $this->assertSame(1, Asteroid::count());
        $this->assertSame(1, CloseApproachEvent::count());
    }

    public function test_repeated_import_does_not_create_duplicates(): void
    {
        $asteroid = new AsteroidData(
            nasaReferenceId: '2307493',
            name: '307493 (2002 XP90)',
            diameterMin: 513.52,
            diameterMax: 1148.26,
            isPotentiallyHazardous: false,
        );

        $approach = new CloseApproachData(
            date: '2026-11-01',
            datetime: '2026-11-01 00:20:00',
            velocityKmh: 57938.91,
            distanceKm: 45865643.87,
            orbitingBody: 'Earth',
        );

        $nasaApi = Mockery::mock(NasaApiService::class);

        $nasaApi->shouldReceive('getAsteroids')
            ->twice()
            ->with('2026-11-01', '2026-11-01')
            ->andReturn([
                [
                    'asteroid' => $asteroid,
                    'approach' => $approach,
                ],
            ]);

        $this->app->instance(NasaApiService::class, $nasaApi);

        $importService = app(NasaImportService::class);

        $firstResult = $importService->importAsteroids(
            '2026-11-01',
            '2026-11-01'
        );

        $secondResult = $importService->importAsteroids(
            '2026-11-01',
            '2026-11-01'
        );

        $this->assertSame(1, $firstResult);
        $this->assertSame(1, $secondResult);

        $this->assertSame(1, Asteroid::count());
        $this->assertSame(1, CloseApproachEvent::count());
    }

    public function test_reimport_updates_existing_asteroid_and_approach(): void
    {
        $originalAsteroid = new AsteroidData(
            nasaReferenceId: '2307493',
            name: 'Original asteroid name',
            diameterMin: 100.00,
            diameterMax: 200.00,
            isPotentiallyHazardous: false,
        );

        $updatedAsteroid = new AsteroidData(
            nasaReferenceId: '2307493',
            name: 'Updated asteroid name',
            diameterMin: 150.00,
            diameterMax: 250.00,
            isPotentiallyHazardous: true,
        );

        $originalApproach = new CloseApproachData(
            date: '2026-11-01',
            datetime: '2026-11-01 00:20:00',
            velocityKmh: 50000.00,
            distanceKm: 40000000.00,
            orbitingBody: 'Earth',
        );

        $updatedApproach = new CloseApproachData(
            date: '2026-11-01',
            datetime: '2026-11-01 00:20:00',
            velocityKmh: 55000.00,
            distanceKm: 35000000.00,
            orbitingBody: 'Earth',
        );

        $nasaApi = Mockery::mock(NasaApiService::class);

        $nasaApi->shouldReceive('getAsteroids')
            ->twice()
            ->andReturn(
                [
                    [
                        'asteroid' => $originalAsteroid,
                        'approach' => $originalApproach,
                    ],
                ],
                [
                    [
                        'asteroid' => $updatedAsteroid,
                        'approach' => $updatedApproach,
                    ],
                ]
            );

        $this->app->instance(NasaApiService::class, $nasaApi);

        $importService = app(NasaImportService::class);

        $importService->importAsteroids('2026-11-01', '2026-11-01');

        $originalId = Asteroid::firstOrFail()->id;

        $importService->importAsteroids('2026-11-01', '2026-11-01');

        $this->assertSame(1, Asteroid::count());
        $this->assertSame(1, CloseApproachEvent::count());

        $this->assertDatabaseHas('asteroids', [
            'id' => $originalId,
            'nasa_reference_id' => '2307493',
            'name' => 'Updated asteroid name',
            'estimated_diameter_min_m' => 150.00,
            'estimated_diameter_max_m' => 250.00,
            'is_potentially_hazardous' => 1,
        ]);

        $this->assertDatabaseHas('close_approach_events', [
            'asteroid_id' => $originalId,
            'relative_velocity_kmh' => 55000.00,
            'miss_distance_km' => 35000000.00,
        ]);
    }

    public function test_empty_nasa_response_does_not_create_records(): void
    {
        $nasaApi = Mockery::mock(NasaApiService::class);

        $nasaApi->shouldReceive('getAsteroids')
            ->once()
            ->with('2026-11-01', '2026-11-01')
            ->andReturn([]);

        $this->app->instance(NasaApiService::class, $nasaApi);

        $result = app(NasaImportService::class)->importAsteroids(
            '2026-11-01',
            '2026-11-01'
        );

        $this->assertSame(0, $result);
        $this->assertDatabaseCount('asteroids', 0);
        $this->assertDatabaseCount('close_approach_events', 0);
    }

    public function test_import_rolls_back_when_approach_cannot_be_saved(): void
    {
        $asteroid = new AsteroidData(
            nasaReferenceId: '2307493',
            name: 'Test asteroid',
            diameterMin: 100.00,
            diameterMax: 200.00,
            isPotentiallyHazardous: false,
        );

        $approach = new CloseApproachData(
            date: '2026-11-01',
            datetime: '2026-11-01 00:20:00',
            velocityKmh: 50000.00,
            distanceKm: 40000000.00,
            orbitingBody: str_repeat('A', 300),
        );

        $nasaApi = Mockery::mock(NasaApiService::class);

        $nasaApi->shouldReceive('getAsteroids')
            ->once()
            ->andReturn([
                [
                    'asteroid' => $asteroid,
                    'approach' => $approach,
                ],
            ]);

        $this->app->instance(NasaApiService::class, $nasaApi);

        try {
            app(NasaImportService::class)->importAsteroids(
                '2026-11-01',
                '2026-11-01'
            );

            $this->fail('Expected a database exception.');
        } catch (QueryException $exception) {
            $this->assertDatabaseCount('asteroids', 0);
            $this->assertDatabaseCount('close_approach_events', 0);
        }
    }

    public function test_import_automatically_calculates_hazard_score(): void
    {
        $asteroid = new AsteroidData(
            nasaReferenceId: '100010',
            name: 'Hazard Test Asteroid',
            diameterMin: 100.00,
            diameterMax: 500.00,
            isPotentiallyHazardous: false,
        );

        $approach = new CloseApproachData(
            date: '2026-11-10',
            datetime: '2026-11-10 12:00:00',
            velocityKmh: 50000.00,
            distanceKm: 1000000.00,
            orbitingBody: 'Earth',
        );

        $nasaApi = Mockery::mock(NasaApiService::class);

        $nasaApi->shouldReceive('getAsteroids')
            ->once()
            ->with('2026-11-10', '2026-11-10')
            ->andReturn([
                [
                    'asteroid' => $asteroid,
                    'approach' => $approach,
                ],
            ]);

        $this->app->instance(NasaApiService::class, $nasaApi);

        app(NasaImportService::class)->importAsteroids(
            '2026-11-10',
            '2026-11-10'
        );

        $savedAsteroid = Asteroid::firstOrFail();

        $this->assertSame(
            50.0,
            (float) $savedAsteroid->calculated_hazard_index
        );

        $this->assertDatabaseCount('close_approach_events', 1);
    }

    public function test_reimport_recalculates_hazard_score(): void
    {
        $asteroid = new AsteroidData(
            nasaReferenceId: '100011',
            name: 'Updated Hazard Asteroid',
            diameterMin: 100.00,
            diameterMax: 500.00,
            isPotentiallyHazardous: false,
        );

        $originalApproach = new CloseApproachData(
            date: '2026-11-10',
            datetime: '2026-11-10 12:00:00',
            velocityKmh: 50000.00,
            distanceKm: 1000000.00,
            orbitingBody: 'Earth',
        );

        $updatedApproach = new CloseApproachData(
            date: '2026-11-10',
            datetime: '2026-11-10 12:00:00',
            velocityKmh: 50000.00,
            distanceKm: 250000.00,
            orbitingBody: 'Earth',
        );

        $nasaApi = Mockery::mock(NasaApiService::class);

        $nasaApi->shouldReceive('getAsteroids')
            ->twice()
            ->andReturn(
                [
                    [
                        'asteroid' => $asteroid,
                        'approach' => $originalApproach,
                    ],
                ],
                [
                    [
                        'asteroid' => $asteroid,
                        'approach' => $updatedApproach,
                    ],
                ]
            );

        $this->app->instance(NasaApiService::class, $nasaApi);

        $importService = app(NasaImportService::class);

        $importService->importAsteroids(
            '2026-11-10',
            '2026-11-10'
        );

        $savedAsteroid = Asteroid::firstOrFail();

        $this->assertSame(
            50.0,
            (float) $savedAsteroid->calculated_hazard_index
        );

        $importService->importAsteroids(
            '2026-11-10',
            '2026-11-10'
        );

        $updatedAsteroid = $savedAsteroid->fresh();

        // D=0.5, V=0.5, R=1/(1+0.25)=0.8
        // H=100*(0.30*0.5+0.25*0.5+0.45*0.8)=63.5
        $this->assertSame(
            63.5,
            (float) $updatedAsteroid->calculated_hazard_index
        );

        $this->assertSame(1, Asteroid::count());
        $this->assertSame(1, CloseApproachEvent::count());
    }

    public function test_import_rolls_back_when_hazard_calculation_fails(): void
    {
        $asteroid = new AsteroidData(
            nasaReferenceId: '900001',
            name: 'Rollback Hazard Test',
            diameterMin: 100.00,
            diameterMax: 500.00,
            isPotentiallyHazardous: false,
        );

        $approach = new CloseApproachData(
            date: '2026-11-10',
            datetime: '2026-11-10 12:00:00',
            velocityKmh: 50000.00,
            distanceKm: 1000000.00,
            orbitingBody: 'Earth',
        );

        $nasaApi = Mockery::mock(NasaApiService::class);

        $nasaApi->shouldReceive('getAsteroids')
            ->once()
            ->andReturn([
                [
                    'asteroid' => $asteroid,
                    'approach' => $approach,
                ],
            ]);

        $this->app->instance(NasaApiService::class, $nasaApi);

        // Імітуємо помилку під час обчислення Hazard Score
        $hazardUpdater = Mockery::mock(
            AsteroidHazardUpdater::class
        );

        $hazardUpdater->shouldReceive('update')
            ->once()
            ->andThrow(new \RuntimeException(
                'Hazard calculation failed.'
            ));

        $this->app->instance(
            AsteroidHazardUpdater::class,
            $hazardUpdater
        );

        $this->expectException(\RuntimeException::class);

        try {
            app(NasaImportService::class)->importAsteroids(
                '2026-11-10',
                '2026-11-10'
            );
        } finally {
            $this->assertDatabaseCount('asteroids', 0);
            $this->assertDatabaseCount('close_approach_events', 0);
        }
    }
}
