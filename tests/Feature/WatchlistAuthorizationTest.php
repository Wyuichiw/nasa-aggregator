<?php

namespace Tests\Feature;

use App\Models\Asteroid;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchlistAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createWatchlist(User $user): Watchlist
    {
        $asteroid = Asteroid::create([
            'nasa_reference_id' => 'TEST-12345',
            'name' => 'Test Asteroid',
            'estimated_diameter_min_m' => 100,
            'estimated_diameter_max_m' => 200,
            'is_potentially_hazardous' => false,
        ]);

        return Watchlist::create([
            'user_id' => $user->id,
            'asteroid_id' => $asteroid->id,
            'notes' => 'Original note',
        ]);
    }

    public function test_guest_cannot_view_watchlist(): void
    {
        $this->get(route('watchlist.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_own_watchlist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('watchlist.index'))
            ->assertOk()
            ->assertViewIs('watchlist.index');
    }

    public function test_user_cannot_update_another_users_watchlist(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $watchlist = $this->createWatchlist($owner);

        $this->actingAs($otherUser)
            ->patch(route('watchlist.update', $watchlist), [
                'notes' => 'Unauthorized change',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('user_watchlist', [
            'id' => $watchlist->id,
            'notes' => 'Original note',
        ]);
    }

    public function test_user_cannot_delete_another_users_watchlist(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $watchlist = $this->createWatchlist($owner);

        $this->actingAs($otherUser)
            ->delete(route('watchlist.destroy', $watchlist))
            ->assertForbidden();

        $this->assertDatabaseHas('user_watchlist', [
            'id' => $watchlist->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_guest_cannot_modify_watchlist(): void
    {
        $owner = User::factory()->create();
        $watchlist = $this->createWatchlist($owner);

        $this->post(route('watchlist.store'), [
            'asteroid_id' => $watchlist->asteroid_id,
        ])->assertRedirect(route('login'));

        $this->patch(route('watchlist.update', $watchlist), [
            'notes' => 'Unauthorized change',
        ])->assertRedirect(route('login'));

        $this->delete(route('watchlist.destroy', $watchlist))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('user_watchlist', [
            'id' => $watchlist->id,
            'user_id' => $owner->id,
            'notes' => 'Original note',
        ]);

        $this->assertDatabaseCount('user_watchlist', 1);
    }
}
