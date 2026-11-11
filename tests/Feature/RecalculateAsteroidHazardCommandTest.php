<?php

namespace Tests\Feature;

use App\Models\Asteroid;
use App\Models\CloseApproachEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculateAsteroidHazardCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createAsteroid(
        string $nasaId,
        float $diameter = 500
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
        float $velocity = 50000,
        float $distance = 1000000
    ): void {
        CloseApproachEvent::create([
            'asteroid_id' => $asteroid->id,
            'close_approach_date' => substr($datetime, 0, 10),
            'close_approach_datetime' => $datetime,
            'relative_velocity_kmh' => $velocity,
            'miss_distance_km' => $distance,
            'orbiting_body' => 'Earth',
        ]);
    }

    public function test_recalculates_hazard_scores_for_all_asteroids(): void
    {
        $first = $this->createAsteroid('500001');
        $second = $this->createAsteroid('500002', 1000);

        $this->createApproach($first, '2026-11-01 12:00:00');
        $this->createApproach($second, '2026-11-02 12:00:00');

        $this->artisan('nasa:recalculate-hazard')
            ->expectsOutput(
                'Hazard scores recalculated for 2 asteroids.'
            )
            ->assertSuccessful();

        $this->assertSame(
            50.0,
            (float) $first->fresh()->calculated_hazard_index
        );

        $this->assertSame(
            65.0,
            (float) $second->fresh()->calculated_hazard_index
        );
    }

    public function test_processes_asteroids_in_small_batches(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $asteroid = $this->createAsteroid(
                (string) (600000 + $i)
            );

            $this->createApproach(
                $asteroid,
                sprintf('2026-11-%02d 12:00:00', $i)
            );
        }

        $this->artisan('nasa:recalculate-hazard --chunk=2')
            ->expectsOutput(
                'Hazard scores recalculated for 5 asteroids.'
            )
            ->assertSuccessful();

        $this->assertSame(
            5,
            Asteroid::whereNotNull('calculated_hazard_index')->count()
        );

        $this->assertSame(5, Asteroid::count());
    }

    public function test_can_be_run_repeatedly_without_duplicates(): void
    {
        $asteroid = $this->createAsteroid('700001');

        $this->createApproach(
            $asteroid,
            '2026-11-10 12:00:00'
        );

        $this->artisan('nasa:recalculate-hazard')
            ->assertSuccessful();

        $firstScore = $asteroid->fresh()->calculated_hazard_index;

        $this->artisan('nasa:recalculate-hazard')
            ->assertSuccessful();

        $this->assertSame(
            $firstScore,
            $asteroid->fresh()->calculated_hazard_index
        );

        $this->assertSame(1, Asteroid::count());
        $this->assertSame(1, CloseApproachEvent::count());
    }

    public function test_rejects_invalid_chunk_sizes(): void
    {
        foreach (['0', '-1', '1001', 'abc'] as $chunk) {
            $this->artisan(
                "nasa:recalculate-hazard --chunk={$chunk}"
            )
                ->expectsOutput(
                    'Chunk size must be an integer between 1 and 1000.'
                )
                ->assertFailed();
        }
    }

    public function test_handles_empty_database(): void
    {
        $this->artisan('nasa:recalculate-hazard')
            ->expectsOutput(
                'Hazard scores recalculated for 0 asteroids.'
            )
            ->assertSuccessful();

        $this->assertSame(0, Asteroid::count());
    }
}
