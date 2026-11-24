<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsteroidFilterRequest;
use App\Models\Asteroid;
use App\Models\CloseApproachEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class AsteroidController extends Controller
{
    public function index(AsteroidFilterRequest $request): View
    {
        $filters = $request->validated();

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $hazardous = $filters['hazardous'] ?? null;
        $sortBy = $filters['sort_by'] ?? 'distance';
        $direction = $filters['sort_direction'] ?? 'asc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $approaches = CloseApproachEvent::query()
            ->where('orbiting_body', 'Earth')
            ->when($dateFrom, function (Builder $query) use ($dateFrom) {
                $query->whereDate('close_approach_date', '>=', $dateFrom);
            })
            ->when($dateTo, function (Builder $query) use ($dateTo) {
                $query->whereDate('close_approach_date', '<=', $dateTo);
            });

        $query = Asteroid::query()
            ->addSelect([
                'min_distance_km' => (clone $approaches)
                    ->selectRaw('MIN(miss_distance_km)')
                    ->whereColumn('asteroid_id', 'asteroids.id'),

                'max_velocity_kmh' => (clone $approaches)
                    ->selectRaw('MAX(relative_velocity_kmh)')
                    ->whereColumn('asteroid_id', 'asteroids.id'),
            ]);

        // Якщо задано дати, залишаємо лише астероїди
        // зі зближеннями у вибраному періоді.
        if ($dateFrom || $dateTo) {
            $query->whereHas('closeApproachEvents', function (Builder $query) use ($dateFrom, $dateTo) {
                $query->where('orbiting_body', 'Earth')
                    ->when($dateFrom, fn (Builder $q) => $q->whereDate('close_approach_date', '>=', $dateFrom))
                    ->when($dateTo, fn (Builder $q) => $q->whereDate('close_approach_date', '<=', $dateTo));
            });
        }

        if ($hazardous !== null) {
            $query->where('is_potentially_hazardous', (bool) $hazardous);
        }

        $sortColumns = [
            'distance' => 'min_distance_km',
            'velocity' => 'max_velocity_kmh',
            'diameter' => 'estimated_diameter_max_m',
        ];

        $query->orderBy($sortColumns[$sortBy], $direction)
            ->orderBy('asteroids.id');

        $asteroids = $query
            ->paginate($perPage)
            ->withQueryString();

        // Перевіряємо Watchlist лише для астероїдів поточної сторінки.
        $watchedAsteroidIds = $request->user()
            ->watchedAsteroids()
            ->whereIn('asteroids.id', $asteroids->getCollection()->pluck('id'))
            ->pluck('asteroids.id');

        return view('asteroids.index', compact(
            'asteroids',
            'filters',
            'watchedAsteroidIds'
        ));
    }
}
