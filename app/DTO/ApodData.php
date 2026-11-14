<?php

namespace App\DTO;

use App\Services\ApodMediaUrlExtractor;
use DateTimeImmutable;
use InvalidArgumentException;

readonly class ApodData
{
    public function __construct(
        public string $date,
        public string $title,
        public string $explanation,
        public string $mediaType,
        public string $sourceUrl,
        public ?string $copyright,
    ) {}

    public static function fromArray(array $data): self
    {
        $date = $data['date'] ?? null;
        $title = $data['title'] ?? null;
        $explanation = $data['explanation'] ?? null;
        $mediaType = $data['media_type'] ?? null;

        if (
            ! is_string($date) ||
            ! self::isValidDate($date) ||
            ! is_string($title) ||
            trim($title) === '' ||
            ! is_string($explanation) ||
            trim($explanation) === '' ||
            ! in_array($mediaType, ['image', 'video'], true)
        ) {
            throw new InvalidArgumentException(
                'Invalid NASA APOD data.'
            );
        }

        if ($mediaType === 'image') {
            $sourceUrl = $data['hdurl'] ?? null;

            if (! self::isValidUrl($sourceUrl)) {
                $candidateUrl = $data['url'] ?? null;

                $sourceUrl = self::isDirectImageUrl($candidateUrl)
                    ? $candidateUrl
                    : null;
            }
        } else {
            $sourceUrl = null;

            $basicHtml = $data['basic_html'] ?? null;

            if (is_string($basicHtml) && trim($basicHtml) !== '') {
                $sourceUrl = ApodMediaUrlExtractor::extract($basicHtml);
            }

            if (! self::isValidUrl($sourceUrl)) {
                $candidateUrl = $data['url'] ?? null;

                $sourceUrl = self::isDirectVideoUrl($candidateUrl)
                    ? $candidateUrl
                    : null;
            }
        }

        if (! self::isValidUrl($sourceUrl)) {
            throw new InvalidArgumentException(
                'Invalid NASA APOD media URL.'
            );
        }

        $copyright = $data['copyright']
            ?? $data['credit']
            ?? null;

        if ($copyright !== null && ! is_string($copyright)) {
            throw new InvalidArgumentException(
                'Invalid NASA APOD copyright.'
            );
        }

        return new self(
            date: $date,
            title: trim($title),
            explanation: $explanation,
            mediaType: $mediaType,
            sourceUrl: $sourceUrl,
            copyright: $copyright,
        );
    }

    private static function isValidUrl(mixed $url): bool
    {
        return is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(
                parse_url($url, PHP_URL_SCHEME),
                ['http', 'https'],
                true
            );
    }

    private static function isDirectImageUrl(mixed $url): bool
    {
        if (! self::isValidUrl($url)) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path)
            && preg_match(
                '/\.(jpg|jpeg|png|gif|webp|tif|tiff)$/i',
                $path
            ) === 1;
    }

    private static function isDirectVideoUrl(mixed $url): bool
    {
        if (! self::isValidUrl($url)) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (
            is_string($path)
            && preg_match('/\.(mp4|webm|mov)$/i', $path) === 1
        ) {
            return true;
        }

        return in_array(
            $host,
            [
                'www.youtube.com',
                'youtube.com',
                'youtu.be',
                'player.vimeo.com',
            ],
            true
        );
    }

    private static function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date;
    }
}
