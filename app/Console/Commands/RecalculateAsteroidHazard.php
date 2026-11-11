<?php

namespace App\Console\Commands;

use App\Models\Asteroid;
use App\Services\AsteroidHazardUpdater;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nasa:recalculate-hazard {--chunk=100 : Number of asteroids per batch}')]
#[Description('Recalculate stored hazard scores for all asteroids')]
class RecalculateAsteroidHazard extends Command
{
    public function handle(AsteroidHazardUpdater $hazardUpdater): int
    {
        $chunk = filter_var(
            $this->option('chunk'),
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 1000,
                ],
            ]
        );

        if ($chunk === false) {
            $this->error('Chunk size must be an integer between 1 and 1000.');

            return self::FAILURE;
        }

        $processed = 0;

        Asteroid::query()
            ->select('id')
            ->chunkById($chunk, function ($asteroids) use (
                $hazardUpdater,
                &$processed
            ) {
                $hazardUpdater->update(
                    $asteroids->pluck('id')->all()
                );

                $processed += $asteroids->count();
            });

        $this->info(
            "Hazard scores recalculated for {$processed} asteroids."
        );

        return self::SUCCESS;
    }
}
