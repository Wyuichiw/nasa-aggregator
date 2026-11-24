<?php

namespace App\Http\Controllers;

use App\Models\NasaMedia;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        // Перевіряємо параметри пошуку та фільтрації.
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'tag' => ['nullable', 'integer', Rule::exists('tags', 'id')],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim($filters['search'] ?? '');
        $tagId = $filters['tag'] ?? null;

        // Отримуємо медіафайли разом із тегами.
        $query = NasaMedia::query()->with('tags');

        // Повнотекстовий пошук через MySQL FULLTEXT.
        if ($search !== '') {
            $query->whereFullText(
                ['title', 'explanation'],
                $search
            );
        }

        // Фільтрація за вибраним тегом.
        if ($tagId !== null) {
            $query->whereHas('tags', function ($query) use ($tagId) {
                $query->where('tags.id', $tagId);
            });
        }

        // Новіші медіафайли відображаються першими.
        $media = $query
            ->orderByDesc('media_date')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $tags = Tag::query()
            ->orderBy('name')
            ->get();

        return view('media.index', [
            'media' => $media,
            'tags' => $tags,
            'filters' => $filters,
        ]);
    }
}
