<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Watchlist;

class WatchlistPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Watchlist $watchlist): bool
    {
        return $user->id === $watchlist->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Watchlist $watchlist): bool
    {
        return $user->id === $watchlist->user_id;
    }

    public function delete(User $user, Watchlist $watchlist): bool
    {
        return $user->id === $watchlist->user_id;
    }
}
