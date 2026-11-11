<?php

namespace Tests\Unit;

use App\Services\HazardScoreService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HazardScoreServiceTest extends TestCase
{
    private HazardScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new HazardScoreService;
    }

    public function test_calculates_expected_hazard_score(): void
    {
        $score = $this->service->calculate(
            500,
            50000,
            1000000
        );

        $this->assertSame(50.0, $score);
    }

    public function test_score_is_always_between_zero_and_one_hundred(): void
    {
        $cases = [
            [0, 0, 0],
            [100, 10000, 10000000],
            [1000, 100000, 0],
            [5000, 200000, 100],
            [100, 5000, 100000000],
        ];

        foreach ($cases as [$diameter, $velocity, $distance]) {
            $score = $this->service->calculate(
                $diameter,
                $velocity,
                $distance
            );

            $this->assertGreaterThanOrEqual(0, $score);
            $this->assertLessThanOrEqual(100, $score);
        }
    }

    public function test_maximum_parameters_produce_score_of_one_hundred(): void
    {
        $score = $this->service->calculate(
            1000,
            100000,
            0
        );

        $this->assertSame(100.0, $score);
    }

    public function test_larger_diameter_increases_score(): void
    {
        $smaller = $this->service->calculate(100, 50000, 1000000);
        $larger = $this->service->calculate(500, 50000, 1000000);

        $this->assertGreaterThan($smaller, $larger);
    }

    public function test_higher_velocity_increases_score(): void
    {
        $slower = $this->service->calculate(500, 10000, 1000000);
        $faster = $this->service->calculate(500, 50000, 1000000);

        $this->assertGreaterThan($slower, $faster);
    }

    public function test_shorter_distance_increases_score(): void
    {
        $farther = $this->service->calculate(500, 50000, 10000000);
        $closer = $this->service->calculate(500, 50000, 1000000);

        $this->assertGreaterThan($farther, $closer);
    }

    public function test_rejects_negative_values(): void
    {
        foreach ([
            [-1, 50000, 1000000],
            [500, -1, 1000000],
            [500, 50000, -1],
        ] as $parameters) {
            try {
                $this->service->calculate(...$parameters);

                $this->fail('Expected InvalidArgumentException.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_rejects_non_finite_values(): void
    {
        foreach ([
            [INF, 50000, 1000000],
            [500, NAN, 1000000],
            [500, 50000, -INF],
        ] as $parameters) {
            try {
                $this->service->calculate(...$parameters);

                $this->fail('Expected InvalidArgumentException.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }
}
