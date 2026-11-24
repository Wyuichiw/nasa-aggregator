<?php

namespace Tests\Feature;

use App\Models\NasaMedia;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use DatabaseMigrations;

    private function createMedia(
        string $date,
        string $title,
        string $explanation = 'Astronomy picture description'
    ): NasaMedia {
        return NasaMedia::create([
            'media_date' => $date,
            'title' => $title,
            'explanation' => $explanation,
            'media_type' => 'image',
            'source_url' => 'https://example.com/image.jpg',
        ]);
    }

    public function test_database_is_separate_for_testing(): void
    {
        $database = DB::connection()
            ->getDatabaseName();

        $this->assertSame(
            'testing',
            $database,
            'PHPUnit must use the separate testing database.'
        );
    }

    public function test_guest_cannot_access_media_library(): void
    {
        $this->get(route('media.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_empty_library(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('media.index'))
            ->assertOk()
            ->assertViewIs('media.index')
            ->assertSee('No NASA media found.');
    }

    public function test_media_library_displays_saved_apod(): void
    {
        $user = User::factory()->create();

        $this->createMedia(
            '2026-11-01',
            'Beautiful Orion Nebula'
        );

        $this->actingAs($user)
            ->get(route('media.index'))
            ->assertOk()
            ->assertSee('Beautiful Orion Nebula')
            ->assertSee('01.11.2026');
    }

    public function test_fulltext_search_finds_media_by_title(): void
    {
        $user = User::factory()->create();

        $this->createMedia(
            '2026-11-01',
            'Spectacular Galaxy Formation'
        );

        $this->createMedia(
            '2026-11-02',
            'Beautiful Lunar Surface'
        );

        $response = $this->actingAs($user)
            ->get(route('media.index', [
                'search' => 'Galaxy',
            ]));

        $response->assertOk()
            ->assertSee('Spectacular Galaxy Formation')
            ->assertDontSee('Beautiful Lunar Surface');
    }

    public function test_fulltext_search_finds_media_by_description(): void
    {
        $user = User::factory()->create();

        $this->createMedia(
            '2026-11-03',
            'Deep Space Observation',
            'The telescope captured a magnificent supernova.'
        );

        $this->actingAs($user)
            ->get(route('media.index', [
                'search' => 'supernova',
            ]))
            ->assertOk()
            ->assertSee('Deep Space Observation');
    }

    public function test_fulltext_search_returns_empty_results(): void
    {
        $user = User::factory()->create();

        $this->createMedia(
            '2026-11-04',
            'Orion Nebula'
        );

        $this->actingAs($user)
            ->get(route('media.index', [
                'search' => 'Jupiter',
            ]))
            ->assertOk()
            ->assertSee('No NASA media found.');
    }

    public function test_media_library_filters_by_tag(): void
    {
        $user = User::factory()->create();

        $galaxyTag = Tag::create(['name' => 'Galaxy']);
        $marsTag = Tag::create(['name' => 'Mars']);

        $galaxyMedia = $this->createMedia(
            '2026-11-05',
            'Andromeda Galaxy'
        );

        $marsMedia = $this->createMedia(
            '2026-11-06',
            'Mars Surface'
        );

        $galaxyMedia->tags()->attach($galaxyTag->id);
        $marsMedia->tags()->attach($marsTag->id);

        $this->actingAs($user)
            ->get(route('media.index', [
                'tag' => $galaxyTag->id,
            ]))
            ->assertOk()
            ->assertSee('Andromeda Galaxy')
            ->assertDontSee('Mars Surface');
    }

    public function test_media_library_combines_search_and_tag_filter(): void
    {
        $user = User::factory()->create();

        $galaxyTag = Tag::create(['name' => 'Galaxy']);
        $marsTag = Tag::create(['name' => 'Mars']);

        $galaxyMedia = $this->createMedia(
            '2026-11-07',
            'Spiral Galaxy Discovery'
        );

        $marsMedia = $this->createMedia(
            '2026-11-08',
            'Galaxy Observation from Mars'
        );

        $galaxyMedia->tags()->attach($galaxyTag->id);
        $marsMedia->tags()->attach($marsTag->id);

        $response = $this->actingAs($user)
            ->get(route('media.index', [
                'search' => 'Galaxy',
                'tag' => $galaxyTag->id,
            ]));

        $response->assertOk();

        $response->assertViewHas('media', function ($media) {
            return $media->total() === 1
                && $media->first()->title === 'Spiral Galaxy Discovery';
        });
    }

    public function test_media_library_paginates_by_twelve(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 15; $i++) {
            $this->createMedia(
                '2026-11-'.str_pad(
                    (string) $i,
                    2,
                    '0',
                    STR_PAD_LEFT
                ),
                'NASA Picture '.$i
            );
        }

        $response = $this->actingAs($user)
            ->get(route('media.index', [
                'page' => 2,
            ]));

        $response->assertOk();

        $response->assertViewHas('media', function ($media) {
            return $media->total() === 15
                && $media->count() === 3
                && $media->currentPage() === 2;
        });
    }

    public function test_media_library_rejects_invalid_tag(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('media.index', [
                'tag' => 999999,
            ]))
            ->assertSessionHasErrors('tag');
    }

    public function test_media_library_rejects_invalid_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('media.index', [
                'page' => 0,
            ]))
            ->assertSessionHasErrors('page');
    }
}
