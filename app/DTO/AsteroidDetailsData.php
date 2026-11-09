<?php

namespace App\DTO;

readonly class AsteroidDetailsData
{
    public function __construct(
        public AsteroidData $asteroid,
        public OrbitalData $orbit,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            asteroid: AsteroidData::fromArray($data),
            orbit: OrbitalData::fromArray(
                $data['orbital_data'] ?? []
            ),
        );
    }
}
