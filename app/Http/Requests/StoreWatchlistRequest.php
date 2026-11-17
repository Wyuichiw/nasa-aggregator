<?php

namespace App\Http\Requests;

use App\Models\Watchlist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWatchlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Watchlist::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'asteroid_id' => [
                'required',
                'integer',
                Rule::exists('asteroids', 'id'),
                Rule::unique('user_watchlist', 'asteroid_id')
                    ->where('user_id', $this->user()->id),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
