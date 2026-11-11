<?php

namespace Tests\Feature;

use App\Models\Asteroid;
use App\Models\CloseApproachEvent;
use App\Services\AsteroidAnalyticsService;
use App\Services\HazardScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AsteroidAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createAsteroid(
        string $nasaId,
        bool $hazardous = false
    ): Asteroid {
        return Asteroid::create([
            'nasa_reference_id' => $nasaId,
            'name' => 'Asteroid '.$nasaId,
            'estimated_diameter_min_m' => 100,
            'estimated_diameter_max_m' => 500,
            'is_potentially_hazardous' => $hazardous,
        ]);
    }

    private function createApproach(
        Asteroid $asteroid,
        string $datetime,
        float $velocity,
        float $distance,
        string $body = 'Earth'
    ): void {
        CloseApproachEvent::create([
            'asteroid_id' => $asteroid->id,
            'close_approach_date' => substr($datetime, 0, 10),
            'close_approach_datetime' => $datetime,
            'relative_velocity_kmh' => $velocity,
            'miss_distance_km' => $distance,
            'orbiting_body' => $body,
        ]);
    }

    public function test_calculates_monthly_statistics(): void
    {
        $first = $this->createAsteroid('200001', true);
        $second = $this->createAsteroid('200002', false);

        $this->createApproach(
            $first, '2026-11-05 12:00:00', 40000, 1000000
        );

        $this->createApproach(
            $first, '2026-11-10 12:00:00', 60000, 2000000
        );

        $this->createApproach(
            $second, '2026-11-15 12:00:00', 50000, 3000000
        );

        $stats = app(AsteroidAnalyticsService::class)
            ->monthlyStatistics(2026, 11);

        $this->assertSame(3, $stats['approaches_count']);
        $this->assertSame(2, $stats['asteroids_count']);
        $this->assertSame(1, $stats['hazardous_asteroids_count']);

        $this->assertSame(
            50000.0,
            $stats['average_velocity_kmh']
        );

        $this->assertSame(
            1000000.0,
            $stats['minimum_distance_km']
        );
    }

    public function test_calculates_yearly_statistics(): void
    {
        $asteroid = $this->createAsteroid('200003', true);

        $this->createApproach(
            $asteroid, '2026-02-10 12:00:00', 20000, 5000000
        );

        $this->createApproach(
            $asteroid, '2026-11-10 12:00:00', 60000, 1000000
        );

        // Подія за межами 2026 року
        $this->createApproach(
            $asteroid, '2027-01-10 12:00:00', 100000, 100
        );

        $stats = app(AsteroidAnalyticsService::class)
            ->yearlyStatistics(2026);

        $this->assertSame(2, $stats['approaches_count']);
        $this->assertSame(1, $stats['asteroids_count']);
        $this->assertSame(1, $stats['hazardous_asteroids_count']);
        $this->assertSame(40000.0, $stats['average_velocity_kmh']);
        $this->assertSame(1000000.0, $stats['minimum_distance_km']);
    }

    public function test_does_not_count_same_asteroid_multiple_times(): void
    {
        $asteroid = $this->createAsteroid('200004', true);

        $this->createApproach(
            $asteroid, '2026-11-01 12:00:00', 30000, 1000000
        );

        $this->createApproach(
            $asteroid, '2026-11-02 12:00:00', 40000, 2000000
        );

        $this->createApproach(
            $asteroid, '2026-11-03 12:00:00', 50000, 3000000
        );

        $stats = app(AsteroidAnalyticsService::class)
            ->monthlyStatistics(2026, 11);

        $this->assertSame(3, $stats['approaches_count']);
        $this->assertSame(1, $stats['asteroids_count']);
        $this->assertSame(1, $stats['hazardous_asteroids_count']);
    }

    public function test_ignores_approaches_to_other_planets(): void
    {
        $asteroid = $this->createAsteroid('200005', true);

        $this->createApproach(
            $asteroid, '2026-11-01 12:00:00',
            90000, 100, 'Mars'
        );

        $this->createApproach(
            $asteroid, '2026-11-02 12:00:00',
            50000, 1000000, 'Earth'
        );

        $stats = app(AsteroidAnalyticsService::class)
            ->monthlyStatistics(2026, 11);

        $this->assertSame(1, $stats['approaches_count']);
        $this->assertSame(1, $stats['asteroids_count']);
        $this->assertSame(50000.0, $stats['average_velocity_kmh']);
        $this->assertSame(1000000.0, $stats['minimum_distance_km']);
    }

    public function test_returns_null_measurements_for_empty_period(): void
    {
        $stats = app(AsteroidAnalyticsService::class)
            ->monthlyStatistics(2026, 12);

        $this->assertSame(0, $stats['approaches_count']);
        $this->assertSame(0, $stats['asteroids_count']);
        $this->assertSame(0, $stats['hazardous_asteroids_count']);
        $this->assertNull($stats['average_velocity_kmh']);
        $this->assertNull($stats['minimum_distance_km']);
    }

    public function test_rejects_invalid_month(): void
    {
        $service = app(AsteroidAnalyticsService::class);

        foreach ([0, 13, -1] as $month) {
            try {
                $service->monthlyStatistics(2026, $month);

                $this->fail('Expected InvalidArgumentException.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_rejects_invalid_year(): void
    {
        $service = app(AsteroidAnalyticsService::class);

        foreach ([0, 9999, -2026] as $year) {
            try {
                $service->yearlyStatistics($year);

                $this->fail('Expected InvalidArgumentException.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_top_hazardous_asteroids_are_sorted_by_score(): void
    {
        $low = $this->createAsteroid('300001');
        $medium = $this->createAsteroid('300002');
        $high = $this->createAsteroid('300003');

        $this->createApproach(
            $low, '2026-11-01 12:00:00',
            10000, 10000000
        );

        $this->createApproach(
            $medium, '2026-11-02 12:00:00',
            50000, 1000000
        );

        $this->createApproach(
            $high, '2026-11-03 12:00:00',
            100000, 100000
        );

        $ranking = app(AsteroidAnalyticsService::class)
            ->topHazardousAsteroids(2026, 11);

        $this->assertCount(3, $ranking);

        $this->assertSame(
            ['300003', '300002', '300001'],
            array_column($ranking, 'nasa_reference_id')
        );

        $this->assertSame(50.0, $ranking[1]['hazard_score']);
    }

    public function test_top_uses_closest_approach_within_selected_month(): void
    {
        $asteroid = $this->createAsteroid('300004');

        // Далеке зближення в листопаді
        $this->createApproach(
            $asteroid, '2026-11-01 12:00:00',
            100000, 10000000
        );

        // Найближче зближення в листопаді
        $this->createApproach(
            $asteroid, '2026-11-10 12:00:00',
            50000, 1000000
        );

        // Ще ближче зближення, але в грудні
        $this->createApproach(
            $asteroid, '2026-12-01 12:00:00',
            100000, 100
        );

        $ranking = app(AsteroidAnalyticsService::class)
            ->topHazardousAsteroids(2026, 11);

        $this->assertCount(1, $ranking);

        $this->assertSame(
            '2026-11-10',
            $ranking[0]['close_approach_date']
        );

        $this->assertSame(
            50.0,
            $ranking[0]['hazard_score']
        );
    }

    public function test_top_returns_only_ten_unique_asteroids(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $asteroid = $this->createAsteroid(
                (string) (400000 + $i)
            );

            $this->createApproach(
                $asteroid,
                sprintf('2026-11-%02d 12:00:00', $i),
                50000,
                1000000 + $i * 10000
            );

            // Додаткове зближення того самого астероїда
            $this->createApproach(
                $asteroid,
                sprintf('2026-11-%02d 18:00:00', $i),
                40000,
                2000000 + $i * 10000
            );
        }

        $ranking = app(AsteroidAnalyticsService::class)
            ->topHazardousAsteroids(2026, 11);

        $this->assertCount(10, $ranking);

        $this->assertCount(
            10,
            array_unique(array_column($ranking, 'asteroid_id'))
        );
    }

    public function test_top_ignores_other_planets_and_missing_diameter(): void
    {
        $earthAsteroid = $this->createAsteroid('300005');

        $marsAsteroid = $this->createAsteroid('300006');

        $withoutDiameter = $this->createAsteroid('300007');

        $withoutDiameter->update([
            'estimated_diameter_max_m' => null,
        ]);

        $this->createApproach(
            $earthAsteroid, '2026-11-01 12:00:00',
            50000, 1000000
        );

        $this->createApproach(
            $marsAsteroid, '2026-11-02 12:00:00',
            100000, 100,
            'Mars'
        );

        $this->createApproach(
            $withoutDiameter, '2026-11-03 12:00:00',
            100000, 100
        );

        $ranking = app(AsteroidAnalyticsService::class)
            ->topHazardousAsteroids(2026, 11);

        $this->assertCount(1, $ranking);

        $this->assertSame(
            '300005',
            $ranking[0]['nasa_reference_id']
        );
    }

    public function test_sql_ranking_matches_php_hazard_calculation(): void
    {
        $calculator = app(HazardScoreService::class);

        $cases = [
            [100, 20000, 10000000],
            [500, 50000, 1000000],
            [1000, 100000, 100000],
            [1500, 120000, 500000],
            [250, 40000, 5000000],
        ];

        foreach ($cases as $index => [$diameter, $velocity, $distance]) {
            $asteroid = $this->createAsteroid(
                (string) (800000 + $index)
            );

            $asteroid->update([
                'estimated_diameter_max_m' => $diameter,
            ]);

            $this->createApproach(
                $asteroid,
                sprintf('2026-11-%02d 12:00:00', $index + 1),
                $velocity,
                $distance
            );
        }

        $ranking = app(AsteroidAnalyticsService::class)
            ->topHazardousAsteroids(2026, 11, true);

        $this->assertCount(5, $ranking);

        foreach ($ranking as $item) {
            $asteroid = Asteroid::findOrFail($item['asteroid_id']);

            $expected = $calculator->calculate(
                (float) $asteroid->estimated_diameter_max_m,
                $item['relative_velocity_kmh'],
                $item['miss_distance_km']
            );

            $this->assertSame(
                $expected,
                $item['hazard_score']
            );

            $this->assertEqualsWithDelta(
                $expected,
                $item['sql_hazard_score'],
                0.01
            );
        }

        $scores = array_column($ranking, 'hazard_score');

        $sortedScores = $scores;
        rsort($sortedScores, SORT_NUMERIC);

        $this->assertSame($sortedScores, $scores);
    }
}
