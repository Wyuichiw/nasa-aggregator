<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWatchlistRequest;
use App\Http\Requests\UpdateWatchlistRequest;
use App\Models\Watchlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WatchlistController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Watchlist::class);

        $watchlist = $request->user()
            ->watchedAsteroids()
            ->orderByPivot('created_at', 'desc')
            ->paginate(10);

        return view('watchlist.index', [
            'asteroids' => $watchlist,
        ]);
    }

    public function store(StoreWatchlistRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $request->user()->watchedAsteroids()->attach(
            $data['asteroid_id'],
            ['notes' => $data['notes'] ?? null]
        );

        return back()->with(
            'success',
            'Asteroid added to your watchlist.'
        );
    }

    public function update(
        UpdateWatchlistRequest $request,
        Watchlist $watchlist
    ): RedirectResponse {
        $watchlist->update($request->validated());

        return back()->with(
            'success',
            'Watchlist note updated.'
        );
    }

    public function destroy(Watchlist $watchlist): RedirectResponse
    {
        Gate::authorize('delete', $watchlist);

        $watchlist->delete();

        return back()->with(
            'success',
            'Asteroid removed from your watchlist.'
        );
    }
}
