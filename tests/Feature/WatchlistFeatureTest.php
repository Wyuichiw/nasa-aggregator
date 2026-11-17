<?php

namespace Tests\Feature;

use App\Models\Asteroid;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchlistFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createAsteroid(int $number): Asteroid
    {
        return Asteroid::create([
            'nasa_reference_id' => 'TEST-'.$number,
            'name' => 'Test Asteroid '.$number,
            'estimated_diameter_min_m' => 100,
            'estimated_diameter_max_m' => 200,
            'is_potentially_hazardous' => false,
        ]);
    }

    public function test_user_can_add_asteroid_to_watchlist(): void
    {
        $user = User::factory()->create();
        $asteroid = $this->createAsteroid(1);

        $this->actingAs($user)
            ->post(route('watchlist.store'), [
                'asteroid_id' => $asteroid->id,
                'notes' => 'Monitoring this asteroid',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_watchlist', [
            'user_id' => $user->id,
            'asteroid_id' => $asteroid->id,
            'notes' => 'Monitoring this asteroid',
        ]);
    }

    public function test_user_cannot_add_same_asteroid_twice(): void
    {
        $user = User::factory()->create();
        $asteroid = $this->createAsteroid(2);

        Watchlist::create([
            'user_id' => $user->id,
            'asteroid_id' => $asteroid->id,
        ]);

        $this->actingAs($user)
            ->post(route('watchlist.store'), [
                'asteroid_id' => $asteroid->id,
            ])
            ->assertSessionHasErrors('asteroid_id');

        $this->assertDatabaseCount('user_watchlist', 1);
    }

    public function test_user_cannot_add_nonexistent_asteroid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('watchlist.store'), [
                'asteroid_id' => 999999,
            ])
            ->assertSessionHasErrors('asteroid_id');

        $this->assertDatabaseCount('user_watchlist', 0);
    }

    public function test_notes_cannot_exceed_2000_characters(): void
    {
        $user = User::factory()->create();
        $asteroid = $this->createAsteroid(3);

        $this->actingAs($user)
            ->post(route('watchlist.store'), [
                'asteroid_id' => $asteroid->id,
                'notes' => str_repeat('A', 2001),
            ])
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseCount('user_watchlist', 0);
    }

    public function test_user_can_update_own_watchlist_note(): void
    {
        $user = User::factory()->create();
        $asteroid = $this->createAsteroid(4);

        $watchlist = Watchlist::create([
            'user_id' => $user->id,
            'asteroid_id' => $asteroid->id,
            'notes' => 'Old note',
        ]);

        $this->actingAs($user)
            ->patch(route('watchlist.update', $watchlist), [
                'notes' => 'Updated note',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_watchlist', [
            'id' => $watchlist->id,
            'notes' => 'Updated note',
        ]);
    }

    public function test_user_can_remove_own_watchlist_entry(): void
    {
        $user = User::factory()->create();
        $asteroid = $this->createAsteroid(5);

        $watchlist = Watchlist::create([
            'user_id' => $user->id,
            'asteroid_id' => $asteroid->id,
        ]);

        $this->actingAs($user)
            ->delete(route('watchlist.destroy', $watchlist))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_watchlist', [
            'id' => $watchlist->id,
        ]);

        $this->assertDatabaseHas('asteroids', [
            'id' => $asteroid->id,
        ]);
    }

    public function test_users_have_separate_watchlists(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $asteroid = $this->createAsteroid(6);

        Watchlist::create([
            'user_id' => $firstUser->id,
            'asteroid_id' => $asteroid->id,
        ]);

        $this->actingAs($secondUser)
            ->get(route('watchlist.index'))
            ->assertOk()
            ->assertDontSee($asteroid->name);

        $this->actingAs($firstUser)
            ->get(route('watchlist.index'))
            ->assertOk()
            ->assertSee($asteroid->name);
    }

    public function test_watchlist_is_paginated_by_ten(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 12; $i++) {
            $asteroid = $this->createAsteroid(100 + $i);

            Watchlist::create([
                'user_id' => $user->id,
                'asteroid_id' => $asteroid->id,
            ]);
        }

        $this->actingAs($user)
            ->get(route('watchlist.index'))
            ->assertOk()
            ->assertViewHas('asteroids', function ($asteroids) {
                return $asteroids->count() === 10
                    && $asteroids->total() === 12
                    && $asteroids->lastPage() === 2;
            });
    }

    public function test_user_cannot_change_watchlist_owner_or_asteroid(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $firstAsteroid = $this->createAsteroid(201);
        $secondAsteroid = $this->createAsteroid(202);

        $watchlist = Watchlist::create([
            'user_id' => $owner->id,
            'asteroid_id' => $firstAsteroid->id,
            'notes' => 'Original note',
        ]);

        $this->actingAs($owner)
            ->patch(route('watchlist.update', $watchlist), [
                'notes' => 'Updated safely',
                'user_id' => $otherUser->id,
                'asteroid_id' => $secondAsteroid->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_watchlist', [
            'id' => $watchlist->id,
            'user_id' => $owner->id,
            'asteroid_id' => $firstAsteroid->id,
            'notes' => 'Updated safely',
        ]);
    }

    public function test_user_can_clear_watchlist_note(): void
    {
        $user = User::factory()->create();
        $asteroid = $this->createAsteroid(203);

        $watchlist = Watchlist::create([
            'user_id' => $user->id,
            'asteroid_id' => $asteroid->id,
            'notes' => 'Note to be removed',
        ]);

        $this->actingAs($user)
            ->patch(route('watchlist.update', $watchlist), [
                'notes' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_watchlist', [
            'id' => $watchlist->id,
            'user_id' => $user->id,
            'asteroid_id' => $asteroid->id,
            'notes' => null,
        ]);
    }

    public function test_update_requires_notes_field(): void
    {
        $user = User::factory()->create();
        $asteroid = $this->createAsteroid(204);

        $watchlist = Watchlist::create([
            'user_id' => $user->id,
            'asteroid_id' => $asteroid->id,
            'notes' => 'Original note',
        ]);

        $this->actingAs($user)
            ->patch(route('watchlist.update', $watchlist), [])
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseHas('user_watchlist', [
            'id' => $watchlist->id,
            'notes' => 'Original note',
        ]);
    }

    public function test_update_rejects_invalid_notes(): void
    {
        $user = User::factory()->create();
        $asteroid = $this->createAsteroid(205);

        $watchlist = Watchlist::create([
            'user_id' => $user->id,
            'asteroid_id' => $asteroid->id,
            'notes' => 'Original note',
        ]);

        $this->actingAs($user)
            ->patch(route('watchlist.update', $watchlist), [
                'notes' => str_repeat('A', 2001),
            ])
            ->assertSessionHasErrors('notes');

        $this->actingAs($user)
            ->patch(route('watchlist.update', $watchlist), [
                'notes' => ['invalid', 'array'],
            ])
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseHas('user_watchlist', [
            'id' => $watchlist->id,
            'notes' => 'Original note',
        ]);
    }
}
