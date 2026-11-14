<?php

namespace App\Jobs;

use App\Models\NasaMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DownloadNasaApodImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    private const MAX_FILE_SIZE = 50 * 1024 * 1024;

    private const ALLOWED_HOSTS = [
        'apod.nasa.gov',
        'science.nasa.gov',
        'assets.science.nasa.gov',
        'api.nasa.gov',
        'images-assets.nasa.gov',
    ];

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        public NasaMedia $nasaMedia
    ) {}

    public function handle(): void
    {
        $lock = Cache::lock(
            'nasa-apod-image:'.$this->nasaMedia->getKey(),
            180
        );

        if (! $lock->get()) {
            throw new RuntimeException(
                'APOD image download is already running.'
            );
        }

        try {
            $this->downloadImage();
        } finally {
            $lock->release();
        }
    }

    private function downloadImage(): void
    {
        $media = $this->nasaMedia->fresh();

        if ($media === null || $media->media_type !== 'image') {
            return;
        }

        $disk = Storage::disk('public');

        if (
            $media->local_storage_path !== null &&
            $disk->exists($media->local_storage_path)
        ) {
            return;
        }

        $url = $media->source_url;

        if (! $this->isAllowedUrl($url)) {
            throw new RuntimeException(
                'APOD image URL is not allowed.'
            );
        }

        // Do not follow redirects to untrusted destinations.
        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->withOptions([
                'allow_redirects' => false,
                'stream' => true,
            ])
            ->get($url);

        if ($response->status() !== 200) {
            throw new RuntimeException(
                'APOD image download failed. HTTP '.
                $response->status()
            );
        }

        $declaredSize = $response->header('Content-Length');

        if (
            $declaredSize !== null &&
            ctype_digit((string) $declaredSize) &&
            (int) $declaredSize > self::MAX_FILE_SIZE
        ) {
            throw new RuntimeException(
                'APOD image exceeds the allowed size.'
            );
        }

        $declaredMime = strtolower(trim(
            explode(
                ';',
                $response->header('Content-Type') ?? ''
            )[0]
        ));

        if (! isset(self::ALLOWED_MIME_TYPES[$declaredMime])) {
            throw new RuntimeException(
                'APOD response is not a supported image.'
            );
        }

        // Read in chunks and stop before exceeding the limit.
        $stream = $response->toPsrResponse()->getBody();

        $content = '';
        $totalBytes = 0;

        while (! $stream->eof()) {
            $chunk = $stream->read(65536);

            if ($chunk === '') {
                if ($stream->eof()) {
                    break;
                }

                throw new RuntimeException(
                    'APOD image stream returned no data before EOF.'
                );
            }

            $totalBytes += strlen($chunk);

            if ($totalBytes > self::MAX_FILE_SIZE) {
                throw new RuntimeException(
                    'APOD image exceeds the allowed size.'
                );
            }

            $content .= $chunk;
        }

        if ($content === '') {
            throw new RuntimeException(
                'APOD image is empty.'
            );
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $actualMime = $finfo->buffer($content);

        if (
            $actualMime !== $declaredMime ||
            ! isset(self::ALLOWED_MIME_TYPES[$actualMime]) ||
            @getimagesizefromstring($content) === false
        ) {
            throw new RuntimeException(
                'APOD image content is invalid.'
            );
        }

        $extension = self::ALLOWED_MIME_TYPES[$actualMime];
        $date = $media->media_date->format('Y-m-d');

        $urlHash = substr(hash('sha256', $url), 0, 16);

        $filename = "apod/{$date}_{$urlHash}.{$extension}";
        $temporaryPath = 'apod/tmp/'.
            $media->getKey().'_'.
            bin2hex(random_bytes(8)).".{$extension}";

        try {
            if (! $disk->put($temporaryPath, $content)) {
                throw new RuntimeException(
                    'Cannot save temporary APOD image.'
                );
            }

            if (! $disk->move($temporaryPath, $filename)) {
                throw new RuntimeException(
                    'Cannot move APOD image to its final path.'
                );
            }

            if (! $disk->exists($filename)) {
                throw new RuntimeException(
                    'Saved APOD image was not found.'
                );
            }

            $updated = NasaMedia::query()
                ->whereKey($media->getKey())
                ->where('media_type', 'image')
                ->where('source_url', $url)
                ->update([
                    'local_storage_path' => $filename,
                ]);

            if ($updated === 0) {
                $disk->delete($filename);

                throw new RuntimeException(
                    'APOD source changed during image download.'
                );
            }
        } finally {
            if ($disk->exists($temporaryPath)) {
                $disk->delete($temporaryPath);
            }
        }
    }

    private function isAllowedUrl(mixed $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        return $scheme === 'https'
            && in_array($host, self::ALLOWED_HOSTS, true)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && (! isset($parts['port']) || $parts['port'] === 443);
    }
}
