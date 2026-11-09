<?php

namespace App\DTO;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

readonly class CloseApproachData
{
    public function __construct(
        public string $date,
        public string $datetime,
        public float $velocityKmh,
        public float $distanceKm,
        public string $orbitingBody,
    ) {}

    public static function fromArray(array $data): self
    {
        $date = $data['close_approach_date'] ?? null;
        $epoch = $data['epoch_date_close_approach'] ?? null;

        $velocity = $data['relative_velocity']['kilometers_per_hour'] ?? null;
        $distance = $data['miss_distance']['kilometers'] ?? null;
        $orbitingBody = $data['orbiting_body'] ?? null;

        if (
            !is_string($date) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
            !is_numeric($epoch) ||
            !is_numeric($velocity) ||
            !is_numeric($distance) ||
            !is_string($orbitingBody) ||
            trim($orbitingBody) === ''
        ) {
            throw new InvalidArgumentException(
                'Invalid NASA close approach data.'
            );
        }

        $dateParts = explode('-', $date);

        if (!checkdate(
            (int) $dateParts[1],
            (int) $dateParts[2],
            (int) $dateParts[0]
        )) {
            throw new InvalidArgumentException(
                'Invalid NASA close approach date.'
            );
        }

        if ((float) $velocity < 0 || (float) $distance < 0) {
            throw new InvalidArgumentException(
                'Velocity and distance cannot be negative.'
            );
        }

        $datetime = CarbonImmutable::createFromTimestampMs(
            (int) $epoch,
            'UTC'
        );

        if ($datetime->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException(
                'Close approach date does not match timestamp.'
            );
        }

        return new self(
            date: $date,
            datetime: $datetime->format('Y-m-d H:i:s'),
            velocityKmh: (float) $velocity,
            distanceKm: (float) $distance,
            orbitingBody: $orbitingBody,
        );
    }
}
