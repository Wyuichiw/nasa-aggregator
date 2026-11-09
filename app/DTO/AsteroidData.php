<?php

namespace App\DTO;

use InvalidArgumentException;

readonly class AsteroidData
{
    public function __construct(
        public string $nasaReferenceId,
        public string $name,
        public ?float $diameterMin,
        public ?float $diameterMax,
        public bool $isPotentiallyHazardous,
    ) {}
    

    public static function fromArray(array $data): self
    {
        if (
            !isset($data['id']) ||
            !is_string($data['id']) ||
            !ctype_digit($data['id']) ||
            !isset($data['name']) ||
            !is_string($data['name']) ||
            trim($data['name']) === '' ||
            !isset($data['is_potentially_hazardous_asteroid']) ||
            !is_bool($data['is_potentially_hazardous_asteroid'])
        ) {
            throw new InvalidArgumentException(
                'Invalid NASA asteroid data.'
            );
        }

        $diameter = $data['estimated_diameter']['meters'] ?? [];

        $min = $diameter['estimated_diameter_min'] ?? null;
        $max = $diameter['estimated_diameter_max'] ?? null;

        if (
            ($min !== null && !is_numeric($min)) ||
            ($max !== null && !is_numeric($max))
        ) {
            throw new InvalidArgumentException(
                'Invalid asteroid diameter.'
            );
        }

        if (
            ($min !== null && (
                !is_finite((float) $min) || (float) $min < 0
            )) ||
            ($max !== null && (
                !is_finite((float) $max) || (float) $max < 0
            )) ||
            ($min !== null && $max !== null &&
                (float) $min > (float) $max)
        ) {
            throw new InvalidArgumentException(
                'Asteroid diameter is out of range.'
            );
        }

        return new self(
            nasaReferenceId: (string) $data['id'],
            name: (string) $data['name'],
            diameterMin: $min !== null ? (float) $min : null,
            diameterMax: $max !== null ? (float) $max : null,
            isPotentiallyHazardous: $data['is_potentially_hazardous_asteroid'],
        );
    }
}
