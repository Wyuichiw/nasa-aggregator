<?php

namespace Tests\Feature;

use App\Jobs\ImportNasaAsteroids;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FetchNasaAsteroidsCommandTest extends TestCase
{
    public function test_default_imports_previous_seven_days(): void
    {
        Queue::fake();

        $this->travelTo(
            \Carbon\CarbonImmutable::parse('2026-11-11 12:00:00', 'UTC')
        );

        $this->artisan('nasa:fetch-asteroids')
            ->assertSuccessful();

        Queue::assertPushed(
            ImportNasaAsteroids::class,
            function (ImportNasaAsteroids $job) {
                return $job->startDate === '2026-11-04'
                    && $job->endDate === '2026-11-10';
            }
        );

        Queue::assertCount(1);
    }

    public function test_one_day_imports_only_yesterday(): void
    {
        Queue::fake();

        $this->travelTo(
            \Carbon\CarbonImmutable::parse('2026-11-11 12:00:00', 'UTC')
        );

        $this->artisan('nasa:fetch-asteroids', [
            '--days' => 1,
        ])->assertSuccessful();

        Queue::assertPushed(
            ImportNasaAsteroids::class,
            function (ImportNasaAsteroids $job) {
                return $job->startDate === '2026-11-10'
                    && $job->endDate === '2026-11-10';
            }
        );
    }

    public function test_invalid_days_are_rejected(): void
    {
        foreach (['0', '8', '-1', 'abc', '2.5'] as $days) {
            Queue::fake();

            $this->artisan('nasa:fetch-asteroids', [
                '--days' => $days,
            ])->assertFailed();

            Queue::assertNothingPushed();
        }
    }
}
