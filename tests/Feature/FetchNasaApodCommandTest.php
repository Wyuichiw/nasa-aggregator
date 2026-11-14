<?php

namespace Tests\Feature;

use App\DTO\ApodData;
use App\Services\NasaApiService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class FetchNasaApodCommandTest extends TestCase
{
    use RefreshDatabase;

    private function mockApod(string $date): void
    {
        $api = Mockery::mock(NasaApiService::class);

        $api->shouldReceive('getApod')
            ->once()
            ->with($date)
            ->andReturn(new ApodData(
                date: $date,
                title: 'NASA Galaxy',
                explanation: 'A galaxy in space.',
                mediaType: 'image',
                sourceUrl: 'https://apod.nasa.gov/test.jpg',
                copyright: 'NASA',
            ));

        $this->app->instance(NasaApiService::class, $api);
    }

    public function test_imports_apod_for_explicit_date(): void
    {
        Queue::fake();
        Storage::fake('public');

        $this->mockApod('2026-11-13');

        $this->artisan('nasa:fetch-apod', [
            '--date' => '2026-11-13',
        ])->assertSuccessful();

        $this->assertDatabaseHas('nasa_media', [
            'media_date' => '2026-11-13',
            'title' => 'NASA Galaxy',
        ]);
    }

    public function test_imports_previous_utc_day_by_default(): void
    {
        Queue::fake();
        Storage::fake('public');

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-11-14 12:00:00',
                'UTC'
            )
        );

        $this->mockApod('2026-11-13');

        $this->artisan('nasa:fetch-apod')
            ->assertSuccessful();
    }

    public function test_rejects_invalid_date(): void
    {
        Queue::fake();

        $this->artisan('nasa:fetch-apod', [
            '--date' => '2026-02-30',
        ])->assertExitCode(2);

        $this->assertDatabaseCount('nasa_media', 0);
    }

    public function test_reports_api_failure(): void
    {
        Queue::fake();

        $api = Mockery::mock(NasaApiService::class);

        $api->shouldReceive('getApod')
            ->once()
            ->andThrow(new \RuntimeException(
                'NASA API unavailable.'
            ));

        $this->app->instance(NasaApiService::class, $api);

        $this->artisan('nasa:fetch-apod', [
            '--date' => '2026-11-13',
        ])->assertFailed();

        $this->assertDatabaseCount('nasa_media', 0);
    }
}
