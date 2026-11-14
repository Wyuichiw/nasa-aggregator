<?php

namespace Tests\Feature;

use App\Jobs\DownloadNasaApodImage;
use App\Models\NasaMedia;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DownloadNasaApodImageTest extends TestCase
{
    use RefreshDatabase;

    private function makeMedia(
        string $date = '2026-11-13',
        string $type = 'image',
        string $url = 'https://apod.nasa.gov/test.png'
    ): NasaMedia {
        return NasaMedia::create([
            'media_date' => $date,
            'title' => 'Test NASA APOD',
            'explanation' => 'Test media description.',
            'media_type' => $type,
            'source_url' => $url,
        ]);
    }

    private function validPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwC'.
            'AAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        );
    }

    public function test_downloads_and_saves_valid_image(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response(
                $this->validPng(),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $media = $this->makeMedia();

        (new DownloadNasaApodImage($media))->handle();

        $expectedHash = substr(
            hash('sha256', 'https://apod.nasa.gov/test.png'),
            0,
            16
        );

        $expectedPath = "apod/2026-11-13_{$expectedHash}.png";

        $this->assertSame(
            $expectedPath,
            $media->fresh()->local_storage_path
        );

        Storage::disk('public')->assertExists($expectedPath);

        Http::assertSentCount(1);
    }

    public function test_skips_videos(): void
    {
        Storage::fake('public');
        Http::fake();

        $media = $this->makeMedia(
            type: 'video',
            url: 'https://www.youtube.com/watch?v=123'
        );

        (new DownloadNasaApodImage($media))->handle();

        $this->assertNull(
            $media->fresh()->local_storage_path
        );

        Http::assertNothingSent();
    }

    public function test_does_not_download_existing_file_again(): void
    {
        Storage::fake('public');
        Http::fake();

        Storage::disk('public')->put(
            'apod/2026-11-13.png',
            $this->validPng()
        );

        $media = $this->makeMedia();

        $media->update([
            'local_storage_path' => 'apod/2026-11-13.png',
        ]);

        (new DownloadNasaApodImage($media))->handle();

        Http::assertNothingSent();
    }

    public function test_rejects_http_error(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response('Rate limited', 429),
        ]);

        $media = $this->makeMedia();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'APOD image download failed. HTTP 429'
        );

        (new DownloadNasaApodImage($media))->handle();
    }

    public function test_rejects_unsafe_url(): void
    {
        Storage::fake('public');
        Http::fake();

        $media = $this->makeMedia(
            url: 'http://169.254.169.254/latest/meta-data/'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'APOD image URL is not allowed.'
        );

        (new DownloadNasaApodImage($media))->handle();
    }

    public function test_rejects_http_instead_of_https(): void
    {
        Storage::fake('public');
        Http::fake();

        $media = $this->makeMedia(
            url: 'http://apod.nasa.gov/test.png'
        );

        $this->expectException(RuntimeException::class);

        (new DownloadNasaApodImage($media))->handle();
    }

    public function test_rejects_http_redirect(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response(
                '',
                302,
                ['Location' => 'http://127.0.0.1/private']
            ),
        ]);

        $media = $this->makeMedia();

        try {
            (new DownloadNasaApodImage($media))->handle();

            $this->fail('Redirect should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'HTTP 302',
                $exception->getMessage()
            );
        }

        Http::assertSentCount(1);
    }

    public function test_rejects_fake_image_content(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response(
                '<html>Not an image</html>',
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $media = $this->makeMedia();

        try {
            (new DownloadNasaApodImage($media))->handle();

            $this->fail('Invalid image should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'image content is invalid',
                $exception->getMessage()
            );
        }

        $this->assertNull(
            $media->fresh()->local_storage_path
        );
    }

    public function test_rejects_oversized_content_length(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response(
                $this->validPng(),
                200,
                [
                    'Content-Type' => 'image/png',
                    'Content-Length' => (string) (51 * 1024 * 1024),
                ]
            ),
        ]);

        $media = $this->makeMedia();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'APOD image exceeds the allowed size.'
        );

        (new DownloadNasaApodImage($media))->handle();
    }

    public function test_does_not_run_when_lock_is_held(): void
    {
        Storage::fake('public');
        Http::fake();

        $media = $this->makeMedia();

        $lock = Cache::lock(
            'nasa-apod-image:'.$media->id,
            180
        );

        $this->assertTrue($lock->get());

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(
                'APOD image download is already running.'
            );

            (new DownloadNasaApodImage($media))->handle();
        } finally {
            $lock->release();
        }
    }

    public function test_does_not_save_path_when_source_changes_during_download(): void
    {
        Storage::fake('public');

        $media = $this->makeMedia(
            date: '2026-11-20',
            url: 'https://apod.nasa.gov/old.png'
        );

        Http::fake([
            '*' => function () use ($media) {
                // Simulate NASA source changing during the download.
                $media->update([
                    'source_url' => 'https://apod.nasa.gov/new.png',
                ]);

                return Http::response(
                    $this->validPng(),
                    200,
                    ['Content-Type' => 'image/png']
                );
            },
        ]);

        try {
            (new DownloadNasaApodImage($media))->handle();

            $this->fail(
                'Outdated APOD download should be rejected.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'APOD source changed during image download.',
                $exception->getMessage()
            );
        }

        $media->refresh();

        $this->assertSame(
            'https://apod.nasa.gov/new.png',
            $media->source_url
        );

        $this->assertNull($media->local_storage_path);

        $oldHash = substr(
            hash('sha256', 'https://apod.nasa.gov/old.png'),
            0,
            16
        );

        $oldPath = "apod/2026-11-20_{$oldHash}.png";

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_downloads_image_from_nasa_science_assets_domain(): void
    {
        Storage::fake('public');

        Http::fake([
            'assets.science.nasa.gov/*' => Http::response(
                $this->validPng(),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $media = $this->makeMedia(
            date: '2026-11-21',
            url: 'https://assets.science.nasa.gov/dynamicimage/test.png'
        );

        (new DownloadNasaApodImage($media))->handle();

        $path = $media->fresh()->local_storage_path;

        $this->assertNotNull($path);

        Storage::disk('public')->assertExists($path);

        Http::assertSentCount(1);
    }

    public function test_does_not_save_database_path_when_storage_write_fails(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response(
                $this->validPng(),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $media = $this->makeMedia(
            date: '2026-11-22'
        );

        $disk = \Mockery::mock(
            Filesystem::class
        );

        $disk->shouldReceive('put')
            ->once()
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturn($disk);

        $disk->shouldReceive('exists')
            ->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot save temporary APOD image.'
        );

        try {
            (new DownloadNasaApodImage($media))->handle();
        } finally {
            $this->assertNull(
                $media->fresh()->local_storage_path
            );
        }
    }

    public function test_does_not_save_database_path_when_storage_move_fails(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response(
                $this->validPng(),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);

        $media = $this->makeMedia(
            date: '2026-11-23'
        );

        $disk = \Mockery::mock(
            Filesystem::class
        );

        $disk->shouldReceive('exists')
            ->andReturnUsing(
                fn (string $path): bool => str_starts_with(
                    $path,
                    'apod/tmp/'
                )
            );

        $disk->shouldReceive('put')
            ->once()
            ->andReturn(true);

        $disk->shouldReceive('move')
            ->once()
            ->andReturn(false);

        $disk->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturn($disk);

        try {
            (new DownloadNasaApodImage($media))->handle();

            $this->fail(
                'Expected an exception when moving APOD image fails.'
            );
        } catch (RuntimeException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        $this->assertNull(
            $media->fresh()->local_storage_path
        );
    }
}
