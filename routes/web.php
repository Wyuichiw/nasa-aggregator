<?php

use App\Http\Controllers\AsteroidController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/asteroids', [AsteroidController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('asteroids.index');

Route::get('/media', [MediaController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('media.index');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/watchlist', [WatchlistController::class, 'index'])
        ->name('watchlist.index');

    Route::post('/watchlist', [WatchlistController::class, 'store'])
        ->name('watchlist.store');

    Route::patch('/watchlist/{watchlist}', [WatchlistController::class, 'update'])
        ->name('watchlist.update');

    Route::delete('/watchlist/{watchlist}', [WatchlistController::class, 'destroy'])
        ->name('watchlist.destroy');
});

require __DIR__.'/auth.php';
