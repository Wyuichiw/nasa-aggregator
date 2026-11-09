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
            !is_string($date) ||
            !self::isValidDate($date) ||
            !is_string($title) ||
            trim($title) === '' ||
            !is_string($explanation) ||
            trim($explanation) === '' ||
            !in_array($mediaType, ['image', 'video'], true)
        ) {
            throw new InvalidArgumentException(
                'Invalid NASA APOD data.'
            );
        }

        // У новому APOD API поле url може вести на сторінку NASA.
        // Для фотографій використовуємо прямий URL із hdurl.
        $sourceUrl = $mediaType === 'image'
            ? ($data['hdurl'] ?? null)
            : ApodMediaUrlExtractor::extract(
                is_string($data['basic_html'] ?? null)
                    ? $data['basic_html']
                    : ''
            );

        if (
            !is_string($sourceUrl) ||
            !filter_var($sourceUrl, FILTER_VALIDATE_URL) ||
            !in_array(
                parse_url($sourceUrl, PHP_URL_SCHEME),
                ['http', 'https'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid NASA APOD media URL.'
            );
        }

        $copyright = $data['copyright']
            ?? $data['credit']
            ?? null;

        if ($copyright !== null && !is_string($copyright)) {
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
