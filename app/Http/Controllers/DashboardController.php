<?php

namespace App\Http\Controllers;

use App\Models\CloseApproachEvent;
use App\Models\NasaMedia;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Отримуємо останній доступний запис NASA APOD.
        $apod = NasaMedia::query()
            ->orderByDesc('media_date')
            ->first();

        // Три найближчі майбутні зближення поточного тижня.
        $now = now();
        $endOfWeek = $now->copy()->endOfWeek();

        $upcomingApproaches = CloseApproachEvent::query()
            ->with('asteroid')
            ->where('orbiting_body', 'Earth')
            ->whereBetween('close_approach_datetime', [
                $now,
                $endOfWeek,
            ])
            ->orderBy('close_approach_datetime')
            ->limit(3)
            ->get();

        return view('dashboard', [
            'apod' => $apod,
            'upcomingApproaches' => $upcomingApproaches,
        ]);
    }
}
