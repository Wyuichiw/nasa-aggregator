<?php

namespace Tests\Feature;

use App\Models\Asteroid;
use App\Models\CloseApproachEvent;
use App\Services\AsteroidHazardUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsteroidHazardUpdaterTest extends TestCase
{
    use RefreshDatabase;

    private function createAsteroid(
        string $nasaId,
        ?float $diameter = 500
    ): Asteroid {
        return Asteroid::create([
            'nasa_reference_id' => $nasaId,
            'name' => 'Test Asteroid '.$nasaId,
            'estimated_diameter_min_m' => 100,
            'estimated_diameter_max_m' => $diameter,
            'is_potentially_hazardous' => false,
        ]);
    }

    private function createApproach(
        Asteroid $asteroid,
        string $datetime,
        float $velocity,
        float $distance,
        string $orbitingBody = 'Earth'
    ): void {
        CloseApproachEvent::create([
            'asteroid_id' => $asteroid->id,
            'close_approach_date' => substr($datetime, 0, 10),
            'close_approach_datetime' => $datetime,
            'relative_velocity_kmh' => $velocity,
            'miss_distance_km' => $distance,
            'orbiting_body' => $orbitingBody,
        ]);
    }

    public function test_calculates_and_saves_hazard_score(): void
    {
        $asteroid = $this->createAsteroid('100001');

        $this->createApproach(
            $asteroid,
            '2026-11-10 12:00:00',
            50000,
            1000000
        );

        app(AsteroidHazardUpdater::class)
            ->update([$asteroid->id]);

        $this->assertEquals(
            50.00,
            (float) $asteroid->fresh()->calculated_hazard_index
        );
    }

    public function test_uses_closest_earth_approach(): void
    {
        $asteroid = $this->createAsteroid('100002');

        // Далеке зближення
        $this->createApproach(
            $asteroid,
            '2026-11-10 12:00:00',
            100000,
            10000000
        );

        // Найближче зближення
        $this->createApproach(
            $asteroid,
            '2026-11-11 12:00:00',
            50000,
            1000000
        );

        app(AsteroidHazardUpdater::class)
            ->update([$asteroid->id]);

        // Очікуємо 50.00 саме для найближчої події
        $this->assertEquals(
            50.00,
            (float) $asteroid->fresh()->calculated_hazard_index
        );
    }

    public function test_ignores_approaches_to_other_bodies(): void
    {
        $asteroid = $this->createAsteroid('100003');

        $this->createApproach(
            $asteroid,
            '2026-11-10 12:00:00',
            100000,
            100,
            'Mars'
        );

        $this->createApproach(
            $asteroid,
            '2026-11-11 12:00:00',
            50000,
            1000000,
            'Earth'
        );

        app(AsteroidHazardUpdater::class)
            ->update([$asteroid->id]);

        $this->assertEquals(
            50.00,
            (float) $asteroid->fresh()->calculated_hazard_index
        );
    }

    public function test_saves_null_when_required_data_is_missing(): void
    {
        // Астероїд без діаметра
        $withoutDiameter = $this->createAsteroid(
            '100004',
            null
        );

        $this->createApproach(
            $withoutDiameter,
            '2026-11-10 12:00:00',
            50000,
            1000000
        );

        // Астероїд без зближень із Землею
        $withoutEarthApproach = $this->createAsteroid(
            '100005'
        );

        $this->createApproach(
            $withoutEarthApproach,
            '2026-11-10 12:00:00',
            50000,
            1000000,
            'Mars'
        );

        app(AsteroidHazardUpdater::class)->update([
            $withoutDiameter->id,
            $withoutEarthApproach->id,
        ]);

        $this->assertNull(
            $withoutDiameter->fresh()->calculated_hazard_index
        );

        $this->assertNull(
            $withoutEarthApproach->fresh()->calculated_hazard_index
        );
    }

    public function test_does_not_modify_other_asteroids(): void
    {
        $first = $this->createAsteroid('100006');
        $second = $this->createAsteroid('100007');

        $this->createApproach(
            $first,
            '2026-11-10 12:00:00',
            50000,
            1000000
        );

        $this->createApproach(
            $second,
            '2026-11-11 12:00:00',
            50000,
            1000000
        );

        app(AsteroidHazardUpdater::class)
            ->update([$first->id]);

        $this->assertEquals(
            50.00,
            (float) $first->fresh()->calculated_hazard_index
        );

        $this->assertNull(
            $second->fresh()->calculated_hazard_index
        );
    }
}
