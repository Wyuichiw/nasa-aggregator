<?php

namespace App\Http\Requests;

use App\Models\Watchlist;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWatchlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        $watchlist = $this->route('watchlist');

        return $watchlist instanceof Watchlist
            && $this->user()?->can('update', $watchlist) === true;
    }

    public function rules(): array
    {
        return [
            'notes' => [
                'present',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
