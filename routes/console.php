<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('nasa:fetch-asteroids --days=7')
    ->name('nasa-asteroids-daily-import')
    ->dailyAt('03:00')
    ->timezone('UTC');
