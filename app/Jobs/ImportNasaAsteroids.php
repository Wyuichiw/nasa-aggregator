<?php

namespace App\Jobs;

use App\Services\NasaImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportNasaAsteroids implements ShouldQueue
{
    use Queueable;

    // Максимум 3 спроби виконання.
    public int $tries = 3;

    // Максимальний час виконання — 120 секунд.
    public int $timeout = 120;

    // Затримки перед повторними спробами.
    public function backoff(): array
    {
        return [60, 180];
    }

    public function __construct(
        public readonly string $startDate,
        public readonly string $endDate
    ) {}

    public function handle(NasaImportService $importService): void
    {
        $importService->importAsteroids(
            $this->startDate,
            $this->endDate
        );
    }
}
