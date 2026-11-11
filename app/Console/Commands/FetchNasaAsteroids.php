<?php

namespace App\Console\Commands;

use App\Jobs\ImportNasaAsteroids;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nasa:fetch-asteroids {--days=7 : Number of days to import (1-7)}')]
#[Description('Queue NASA asteroid imports for the previous 1-7 days')]
class FetchNasaAsteroids extends Command
{
    public function handle(): int
    {
        $days = filter_var(
            $this->option('days'),
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 7,
                ],
            ]
        );

        if ($days === false) {
            $this->error('The --days option must be an integer from 1 to 7.');

            return self::FAILURE;
        }

        $today = now('UTC');

        $startDate = $today->copy()
            ->subDays($days)
            ->toDateString();

        $endDate = $today->copy()
            ->subDay()
            ->toDateString();

        ImportNasaAsteroids::dispatch($startDate, $endDate);

        $this->info(
            "NASA asteroid import queued: {$startDate} to {$endDate}"
        );

        return self::SUCCESS;
    }
}
