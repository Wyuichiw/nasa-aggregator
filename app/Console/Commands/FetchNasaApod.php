<?php

namespace App\Console\Commands;

use App\Services\NasaImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

class FetchNasaApod extends Command
{
    protected $signature = 'nasa:fetch-apod
        {--date= : APOD date in YYYY-MM-DD format}';

    protected $description = 'Import NASA APOD metadata and queue image download';

    public function handle(NasaImportService $importService): int
    {
        $date = $this->option('date')
            ?: now('UTC')->subDay()->toDateString();

        try {
            $media = $importService->importApod($date);

            $this->info(
                "APOD imported: {$media->media_date->format('Y-m-d')} "
                ."({$media->media_type})"
            );

            return self::SUCCESS;
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'NASA APOD import failed: '.$exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
