<?php

namespace Tests\Feature;

use App\Jobs\ImportNasaAsteroids;
use App\Services\NasaImportService;
use Mockery;
use Tests\TestCase;
use Illuminate\Support\Facades\Queue;



class ImportNasaAsteroidsJobTest extends TestCase
{
    public function test_job_calls_import_service_with_correct_dates(): void
    {
        $importService = Mockery::mock(NasaImportService::class);

        $importService->shouldReceive('importAsteroids')
            ->once()
            ->with('2026-11-01', '2026-11-07')
            ->andReturn(4);

        $this->app->instance(
            NasaImportService::class,
            $importService
        );

        $job = new ImportNasaAsteroids(
            '2026-11-01',
            '2026-11-07'
        );

        $job->handle(app(NasaImportService::class));
    }

    public function test_job_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        ImportNasaAsteroids::dispatch(
            '2026-11-01',
            '2026-11-07'
        );

        Queue::assertPushed(
            ImportNasaAsteroids::class,
            function (ImportNasaAsteroids $job) {
                return $job->startDate === '2026-11-01'
                    && $job->endDate === '2026-11-07';
            }
        );
    }
}
