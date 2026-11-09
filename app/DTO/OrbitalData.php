<?php

namespace App\DTO;

use InvalidArgumentException;

readonly class OrbitalData
{
    public function __construct(
        public float $eccentricity,
        public float $inclination,
        public float $semiMajorAxis,
        public ?float $orbitalPeriod,
        public string $orbitClass,
    ) {}

    public static function fromArray(array $data): self
    {
        $eccentricity = $data['eccentricity'] ?? null;
        $inclination = $data['inclination'] ?? null;
        $semiMajorAxis = $data['semi_major_axis'] ?? null;
        $orbitalPeriod = $data['orbital_period'] ?? null;
        $orbitClass = $data['orbit_class']['orbit_class_type'] ?? null;

        if (
            !is_numeric($eccentricity) ||
            !is_numeric($inclination) ||
            !is_numeric($semiMajorAxis) ||
            ($orbitalPeriod !== null && !is_numeric($orbitalPeriod)) ||
            !is_string($orbitClass) ||
            trim($orbitClass) === ''
        ) {
            throw new InvalidArgumentException(
                'Invalid NASA orbital data.'
            );
        }

        $eccentricity = (float) $eccentricity;
        $inclination = (float) $inclination;
        $semiMajorAxis = (float) $semiMajorAxis;
        $orbitalPeriod = $orbitalPeriod !== null
            ? (float) $orbitalPeriod
            : null;

        if (
            !is_finite($eccentricity) ||
            !is_finite($inclination) ||
            !is_finite($semiMajorAxis) ||
            ($orbitalPeriod !== null && !is_finite($orbitalPeriod)) ||
            $eccentricity < 0 ||
            $inclination < 0 ||
            $inclination > 180
        ) {
            throw new InvalidArgumentException(
                'NASA orbital parameters are out of range.'
            );
        }

        if ($eccentricity < 1) {
            if (
                $semiMajorAxis <= 0 ||
                $orbitalPeriod === null ||
                $orbitalPeriod <= 0
            ) {
                throw new InvalidArgumentException(
                    'Invalid elliptic orbital parameters.'
                );
            }
        } elseif ($eccentricity > 1) {
            if (
                $semiMajorAxis >= 0 ||
                $orbitalPeriod !== null
            ) {
                throw new InvalidArgumentException(
                    'Invalid hyperbolic orbital parameters.'
                );
            }
        } else {
            if (
                $semiMajorAxis != 0.0 ||
                $orbitalPeriod !== null
            ) {
                throw new InvalidArgumentException(
                    'Invalid parabolic orbital parameters.'
                );
            }
        }

        return new self(
            eccentricity: $eccentricity,
            inclination: $inclination,
            semiMajorAxis: $semiMajorAxis,
            orbitalPeriod: $orbitalPeriod,
            orbitClass: trim($orbitClass),
        );
    }
}
