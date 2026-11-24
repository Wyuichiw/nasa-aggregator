<?php

namespace Tests\Feature;

use App\Models\Asteroid;
use App\Models\CloseApproachEvent;
use App\Models\NasaMedia;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dashboard_without_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard');
        $response->assertViewHas('apod', null);
        $response->assertViewHas('upcomingApproaches', function ($approaches) {
            return $approaches->isEmpty();
        });
        $response->assertSee('No NASA APOD data available yet.');
        $response->assertSee('No upcoming asteroid approaches found.');
    }

    public function test_dashboard_displays_latest_apod(): void
    {
        $user = User::factory()->create();

        NasaMedia::create([
            'media_date' => '2026-11-10',
            'title' => 'Older NASA Picture',
            'explanation' => 'Older picture description.',
            'media_type' => 'image',
            'source_url' => 'https://example.com/old.jpg',
        ]);

        NasaMedia::create([
            'media_date' => '2026-11-11',
            'title' => 'Latest NASA Galaxy',
            'explanation' => 'A beautiful galaxy.',
            'media_type' => 'image',
            'source_url' => 'https://example.com/new.jpg',
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Latest NASA Galaxy');
        $response->assertViewHas('apod', function ($apod) {
            return $apod->title === 'Latest NASA Galaxy';
        });
    }

    public function test_dashboard_displays_three_nearest_future_earth_approaches(): void
    {
        // Фіксуємо понеділок, щоб тест не залежав від дня запуску.
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));

        $user = User::factory()->create();

        $asteroid = Asteroid::create([
            'nasa_reference_id' => 'TEST-100',
            'name' => 'Test Asteroid',
            'estimated_diameter_min_m' => 100,
            'estimated_diameter_max_m' => 200,
            'is_potentially_hazardous' => false,
        ]);

        // Чотири майбутні зближення в межах поточного тижня.
        foreach ([4, 1, 3, 2] as $days) {
            CloseApproachEvent::create([
                'asteroid_id' => $asteroid->id,
                'close_approach_date' => now()->addDays($days)->toDateString(),
                'close_approach_datetime' => now()->addDays($days),
                'relative_velocity_kmh' => 25000 + $days,
                'miss_distance_km' => 1000000 + $days,
                'orbiting_body' => 'Earth',
            ]);
        }

        // Минуле зближення не повинно відображатися.
        CloseApproachEvent::create([
            'asteroid_id' => $asteroid->id,
            'close_approach_date' => now()->subDays(2)->toDateString(),
            'close_approach_datetime' => now()->subDays(2),
            'relative_velocity_kmh' => 10000,
            'miss_distance_km' => 500000,
            'orbiting_body' => 'Earth',
        ]);

        // Зближення з Марсом також виключаємо.
        CloseApproachEvent::create([
            'asteroid_id' => $asteroid->id,
            'close_approach_date' => now()->addDay()->toDateString(),
            'close_approach_datetime' => now()->addDay(),
            'relative_velocity_kmh' => 50000,
            'miss_distance_km' => 100000,
            'orbiting_body' => 'Mars',
        ]);

        // Подія наступного тижня не повинна потрапити в результати.
        CloseApproachEvent::create([
            'asteroid_id' => $asteroid->id,
            'close_approach_date' => now()->addDays(7)->toDateString(),
            'close_approach_datetime' => now()->addDays(7),
            'relative_velocity_kmh' => 60000,
            'miss_distance_km' => 300000,
            'orbiting_body' => 'Earth',
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertViewHas('upcomingApproaches', function ($approaches) {
            $dates = $approaches
                ->pluck('close_approach_datetime')
                ->map(fn ($date) => $date->timestamp)
                ->values()
                ->all();

            $sortedDates = $dates;
            sort($sortedDates);

            return $approaches->count() === 3
                && $approaches->every(
                    fn ($approach) => $approach->orbiting_body === 'Earth'
                        && $approach->close_approach_datetime->between(
                            now(),
                            now()->endOfWeek()
                        )
                )
                && $dates === $sortedDates;
        });
    }

    public function test_dashboard_does_not_include_approaches_from_next_week(): void
    {
        // Субота: поточний тиждень закінчується завтра.
        $this->travelTo(Carbon::parse('2026-11-21 10:00:00'));

        $user = User::factory()->create();

        $asteroid = Asteroid::create([
            'nasa_reference_id' => 'TEST-WEEK',
            'name' => 'Weekly Test Asteroid',
            'estimated_diameter_min_m' => 100,
            'estimated_diameter_max_m' => 200,
            'is_potentially_hazardous' => false,
        ]);

        // Єдине зближення до кінця поточного тижня.
        CloseApproachEvent::create([
            'asteroid_id' => $asteroid->id,
            'close_approach_date' => '2026-11-22',
            'close_approach_datetime' => '2026-11-22 15:00:00',
            'relative_velocity_kmh' => 25000,
            'miss_distance_km' => 1000000,
            'orbiting_body' => 'Earth',
        ]);

        // Зближення наступного понеділка.
        CloseApproachEvent::create([
            'asteroid_id' => $asteroid->id,
            'close_approach_date' => '2026-11-23',
            'close_approach_datetime' => '2026-11-23 09:00:00',
            'relative_velocity_kmh' => 30000,
            'miss_distance_km' => 2000000,
            'orbiting_body' => 'Earth',
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertViewHas('upcomingApproaches', function ($approaches) {
            return $approaches->count() === 1
                && $approaches->first()->close_approach_date
                    ->toDateString() === '2026-11-22';
        });
    }
}
