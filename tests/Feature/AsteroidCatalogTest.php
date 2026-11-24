<?php

namespace Tests\Feature;

use App\Models\Asteroid;
use App\Models\CloseApproachEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsteroidCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function createAsteroid(
        string $nasaId,
        string $name,
        bool $hazardous = false,
        float $diameter = 100
    ): Asteroid {
        return Asteroid::create([
            'nasa_reference_id' => $nasaId,
            'name' => $name,
            'estimated_diameter_min_m' => $diameter / 2,
            'estimated_diameter_max_m' => $diameter,
            'is_potentially_hazardous' => $hazardous,
        ]);
    }

    private function createApproach(
        Asteroid $asteroid,
        string $date,
        float $distance,
        float $velocity,
        string $body = 'Earth'
    ): CloseApproachEvent {
        return CloseApproachEvent::create([
            'asteroid_id' => $asteroid->id,
            'close_approach_date' => $date,
            'close_approach_datetime' => $date.' 12:00:00',
            'relative_velocity_kmh' => $velocity,
            'miss_distance_km' => $distance,
            'orbiting_body' => $body,
        ]);
    }

    public function test_guest_cannot_access_catalog(): void
    {
        $this->get(route('asteroids.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_catalog(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('asteroids.index'))
            ->assertOk()
            ->assertViewIs('asteroids.index')
            ->assertSee('Asteroid Catalog');
    }

    public function test_catalog_does_not_duplicate_asteroids(): void
    {
        $user = User::factory()->create();

        $asteroid = $this->createAsteroid(
            'TEST-101',
            'Unique Asteroid'
        );

        $this->createApproach(
            $asteroid,
            '2026-11-22',
            1000000,
            25000
        );

        $this->createApproach(
            $asteroid,
            '2026-11-23',
            2000000,
            30000
        );

        $response = $this->actingAs($user)
            ->get(route('asteroids.index'));

        $response->assertOk();

        $response->assertViewHas('asteroids', function ($asteroids) {
            return $asteroids->total() === 1
                && $asteroids->count() === 1;
        });
    }

    public function test_catalog_filters_hazardous_asteroids(): void
    {
        $user = User::factory()->create();

        $this->createAsteroid(
            'TEST-201',
            'Hazardous Test Asteroid',
            true
        );

        $this->createAsteroid(
            'TEST-202',
            'Safe Test Asteroid',
            false
        );

        $response = $this->actingAs($user)
            ->get(route('asteroids.index', [
                'hazardous' => 1,
            ]));

        $response->assertOk();
        $response->assertSee('Hazardous Test Asteroid');
        $response->assertDontSee('Safe Test Asteroid');

        $response->assertViewHas('asteroids', function ($asteroids) {
            return $asteroids->total() === 1;
        });
    }

    public function test_catalog_filters_by_approach_dates(): void
    {
        $user = User::factory()->create();

        $inside = $this->createAsteroid(
            'TEST-301',
            'Inside Date Range'
        );

        $outside = $this->createAsteroid(
            'TEST-302',
            'Outside Date Range'
        );

        $this->createApproach(
            $inside,
            '2026-11-22',
            1000000,
            25000
        );

        $this->createApproach(
            $outside,
            '2026-12-05',
            2000000,
            30000
        );

        $response = $this->actingAs($user)
            ->get(route('asteroids.index', [
                'date_from' => '2026-11-21',
                'date_to' => '2026-11-23',
            ]));

        $response->assertOk();
        $response->assertSee('Inside Date Range');
        $response->assertDontSee('Outside Date Range');

        $response->assertViewHas('asteroids', function ($asteroids) {
            return $asteroids->total() === 1;
        });
    }

    public function test_catalog_sorts_by_minimum_distance(): void
    {
        $user = User::factory()->create();

        $far = $this->createAsteroid(
            'TEST-401',
            'Far Asteroid'
        );

        $near = $this->createAsteroid(
            'TEST-402',
            'Near Asteroid'
        );

        $this->createApproach(
            $far,
            '2026-11-22',
            5000000,
            20000
        );

        $this->createApproach(
            $near,
            '2026-11-23',
            1000000,
            30000
        );

        $response = $this->actingAs($user)
            ->get(route('asteroids.index', [
                'sort_by' => 'distance',
                'sort_direction' => 'asc',
            ]));

        $response->assertOk();

        $response->assertViewHas('asteroids', function ($asteroids) {
            return $asteroids->pluck('name')->all() === [
                'Near Asteroid',
                'Far Asteroid',
            ];
        });
    }

    public function test_catalog_sorts_by_maximum_velocity(): void
    {
        $user = User::factory()->create();

        $slow = $this->createAsteroid(
            'TEST-501',
            'Slow Asteroid'
        );

        $fast = $this->createAsteroid(
            'TEST-502',
            'Fast Asteroid'
        );

        $this->createApproach(
            $slow,
            '2026-11-22',
            1000000,
            20000
        );

        $this->createApproach(
            $fast,
            '2026-11-23',
            2000000,
            80000
        );

        $response = $this->actingAs($user)
            ->get(route('asteroids.index', [
                'sort_by' => 'velocity',
                'sort_direction' => 'desc',
            ]));

        $response->assertOk();

        $response->assertViewHas('asteroids', function ($asteroids) {
            return $asteroids->pluck('name')->all() === [
                'Fast Asteroid',
                'Slow Asteroid',
            ];
        });
    }

    public function test_catalog_sorts_by_diameter(): void
    {
        $user = User::factory()->create();

        $this->createAsteroid(
            'TEST-601',
            'Small Asteroid',
            false,
            50
        );

        $this->createAsteroid(
            'TEST-602',
            'Large Asteroid',
            false,
            500
        );

        $response = $this->actingAs($user)
            ->get(route('asteroids.index', [
                'sort_by' => 'diameter',
                'sort_direction' => 'desc',
            ]));

        $response->assertOk();

        $response->assertViewHas('asteroids', function ($asteroids) {
            return $asteroids->pluck('name')->all() === [
                'Large Asteroid',
                'Small Asteroid',
            ];
        });
    }

    public function test_catalog_paginates_results(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 12; $i++) {
            $this->createAsteroid(
                'PAGE-'.$i,
                'Pagination Asteroid '.$i,
                false,
                $i * 10
            );
        }

        $response = $this->actingAs($user)
            ->get(route('asteroids.index', [
                'sort_by' => 'diameter',
                'sort_direction' => 'asc',
                'per_page' => 5,
                'page' => 2,
            ]));

        $response->assertOk();

        $response->assertViewHas('asteroids', function ($asteroids) {
            return $asteroids->total() === 12
                && $asteroids->count() === 5
                && $asteroids->currentPage() === 2;
        });
    }

    public function test_catalog_rejects_invalid_per_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('asteroids.index', [
                'per_page' => 101,
            ]))
            ->assertSessionHasErrors('per_page');
    }

    public function test_catalog_rejects_invalid_sort_field(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('asteroids.index', [
                'sort_by' => 'invalid_column',
            ]))
            ->assertSessionHasErrors('sort_by');
    }

    public function test_catalog_shows_watchlist_status_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $savedAsteroid = $this->createAsteroid(
            'WATCH-101',
            'Saved Asteroid'
        );

        $newAsteroid = $this->createAsteroid(
            'WATCH-102',
            'New Asteroid'
        );

        // Додаємо лише перший астероїд у Watchlist.
        $user->watchedAsteroids()->attach($savedAsteroid->id);

        $response = $this->actingAs($user)
            ->get(route('asteroids.index', [
                'sort_by' => 'diameter',
            ]));

        $response->assertOk();

        // Перевіряємо, що контролер правильно визначив збережений астероїд.
        $response->assertViewHas('watchedAsteroidIds', function ($ids) use (
            $savedAsteroid,
            $newAsteroid
        ) {
            return $ids->contains($savedAsteroid->id)
                && ! $ids->contains($newAsteroid->id);
        });

        // У каталозі присутні обидва астероїди.
        $response->assertSee('Saved Asteroid');
        $response->assertSee('New Asteroid');

        // Для збереженого астероїда відображається відповідний статус.
        $response->assertSee('In Watchlist');

        // Для нового астероїда доступна кнопка додавання.
        $response->assertSee('Add to Watchlist');
    }
}
