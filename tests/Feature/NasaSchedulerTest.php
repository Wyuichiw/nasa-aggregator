<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class NasaSchedulerTest extends TestCase
{
    public function test_daily_nasa_import_is_scheduled(): void
    {
        $events = app(Schedule::class)->events();

        $event = collect($events)->first(
            fn ($event) => str_contains(
                $event->command ?? '',
                'nasa:fetch-asteroids --days=7'
            )
        );

        $this->assertNotNull(
            $event,
            'NASA import scheduled command was not found.'
        );

        $this->assertSame('0 3 * * *', $event->expression);

        $this->assertSame('UTC', $event->timezone);
    }

    public function test_daily_apod_import_is_scheduled(): void
    {
        $events = \Illuminate\Support\Facades\Schedule::events();

        $apodEvent = collect($events)->first(
            fn ($event) => str_contains(
                $event->command ?? '',
                'nasa:fetch-apod'
            )
        );

        $this->assertNotNull(
            $apodEvent,
            'NASA APOD import must be scheduled.'
        );

        $this->assertSame(
            '0 9 * * *',
            $apodEvent->expression
        );

        $this->assertSame(
            'UTC',
            $apodEvent->timezone
        );
    }
}
