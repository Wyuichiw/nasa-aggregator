<?php

namespace Tests\Feature;

use App\DTO\ApodData;
use App\DTO\AsteroidData;
use App\DTO\AsteroidDetailsData;
use App\DTO\CloseApproachData;
use App\DTO\OrbitalData;
use App\Services\NasaApiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class NasaApiServiceTest extends TestCase
{
    public function test_rejects_date_range_longer_than_seven_days(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Date range must contain 1 to 7 days.'
        );

        app(NasaApiService::class)->getAsteroids(
            '2026-11-01',
            '2026-11-09'
        );
    }

    public function test_handles_nasa_rate_limit(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response([
                'error' => 'Rate limit exceeded',
            ], 429),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'NASA API rate limit exceeded.'
        );

        app(NasaApiService::class)->getAsteroidDetails('2307493');
    }

    public function test_gets_asteroid_details_successfully(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response([
                'id' => '2307493',
                'name' => '307493 (2002 XP90)',
                'estimated_diameter' => [
                    'meters' => [
                        'estimated_diameter_min' => 513.52,
                        'estimated_diameter_max' => 1148.26,
                    ],
                ],
                'is_potentially_hazardous_asteroid' => false,
            ], 200),
        ]);

        $asteroid = app(NasaApiService::class)
            ->getAsteroidDetails('2307493');

        $this->assertInstanceOf(
            AsteroidData::class,
            $asteroid
        );

        $this->assertSame('2307493', $asteroid->nasaReferenceId);
        $this->assertSame('307493 (2002 XP90)', $asteroid->name);
        $this->assertEquals(513.52, $asteroid->diameterMin);
        $this->assertEquals(1148.26, $asteroid->diameterMax);
        $this->assertFalse($asteroid->isPotentiallyHazardous);
    }

    public function test_rejects_invalid_asteroid_response(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response([
                'id' => '2307493',
            ], 200),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid NASA asteroid data.'
        );

        app(NasaApiService::class)->getAsteroidDetails('2307493');
    }

    public function test_gets_latest_apod_successfully(): void
    {
        Http::fake([
            'science.nasa.gov/*' => Http::response([
                [
                    'date' => '2026-11-09',
                    'title' => 'Rigel and the Witch Head Nebula',
                    'explanation' => 'A beautiful nebula near Rigel.',
                    'media_type' => 'image',
                    'url' => 'https://science.nasa.gov/image-article/example/',
                    'hdurl' => 'https://assets.science.nasa.gov/example.jpg',
                    'copyright' => 'NASA',
                ],
            ], 200),
        ]);

        $apod = app(NasaApiService::class)->getLatestApod();

        $this->assertInstanceOf(
            ApodData::class,
            $apod
        );

        $this->assertSame('2026-11-09', $apod->date);
        $this->assertSame('Rigel and the Witch Head Nebula', $apod->title);
        $this->assertSame('image', $apod->mediaType);
        $this->assertSame(
            'https://assets.science.nasa.gov/example.jpg',
            $apod->sourceUrl
        );
        $this->assertSame('NASA', $apod->copyright);
    }

    public function test_rejects_invalid_apod_response(): void
    {
        Http::fake([
            'science.nasa.gov/*' => Http::response([
                [
                    'date' => '2026-11-09',
                    'title' => 'Test NASA Photo',
                    'explanation' => 'Test description',
                    'media_type' => 'unknown',
                    'hdurl' => 'https://example.com/photo.jpg',
                ],
            ], 200),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid NASA APOD data.'
        );

        app(NasaApiService::class)->getLatestApod();
    }

    public function test_handles_nasa_server_error(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response([
                'code' => 500,
                'msg' => 'Internal Service Error',
            ], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'NASA API request failed. HTTP 500'
        );

        app(NasaApiService::class)->getAsteroidDetails('2307493');
    }

    public function test_handles_nasa_connection_failure(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::failedConnection(),
        ]);

        $this->expectException(
            ConnectionException::class
        );

        app(NasaApiService::class)->getAsteroidDetails('2307493');
    }

    public function test_accepts_seven_day_date_range(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response([
                'element_count' => 0,
                'near_earth_objects' => [],
            ], 200),
        ]);

        $result = app(NasaApiService::class)->getAsteroids(
            '2026-11-01',
            '2026-11-07'
        );

        $this->assertSame([], $result);

        Http::assertSent(function ($request) {
            return $request->url() ===
                'https://api.nasa.gov/neo/rest/v1/feed'
                .'?start_date=2026-11-01'
                .'&end_date=2026-11-07'
                .'&api_key='.config('services.nasa.key');
        });
    }

    public function test_rejects_invalid_calendar_date(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        app(NasaApiService::class)->getAsteroids(
            '2026-02-31',
            '2026-03-01'
        );
    }

    public function test_gets_full_asteroid_details(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response([
                'id' => '2307493',
                'name' => '307493 (2002 XP90)',
                'estimated_diameter' => [
                    'meters' => [
                        'estimated_diameter_min' => 513.52,
                        'estimated_diameter_max' => 1148.26,
                    ],
                ],
                'is_potentially_hazardous_asteroid' => false,
                'orbital_data' => [
                    'eccentricity' => '0.5857299389',
                    'inclination' => '23.1151895885',
                    'semi_major_axis' => '2.0796148112',
                    'orbital_period' => '1095.4001086',
                    'orbit_class' => [
                        'orbit_class_type' => 'APO',
                    ],
                ],
            ], 200),
        ]);

        $details = app(NasaApiService::class)
            ->getFullAsteroidDetails('2307493');

        $this->assertInstanceOf(
            AsteroidDetailsData::class,
            $details
        );

        $this->assertSame(
            '2307493',
            $details->asteroid->nasaReferenceId
        );

        $this->assertEqualsWithDelta(
            0.5857299389,
            $details->orbit->eccentricity,
            0.000001
        );

        $this->assertEqualsWithDelta(
            23.1151895885,
            $details->orbit->inclination,
            0.000001
        );

        $this->assertSame('APO', $details->orbit->orbitClass);
    }

    public function test_gets_apod_video_successfully(): void
    {
        Http::fake([
            'science.nasa.gov/*' => Http::response([
                [
                    'date' => '2026-10-28',
                    'title' => 'The Mutual Occultation of Ganymede and Io',
                    'explanation' => 'A video of Jupiter moons.',
                    'media_type' => 'video',
                    'url' => 'https://science.nasa.gov/image-article/example/',
                    'hdurl' => 'https://example.com/preview.jpg',
                    'basic_html' => '<video controls><source src="https://example.com/occultation.mp4" type="video/mp4"></video>',
                    'copyright' => 'NASA',
                ],
            ], 200),
        ]);

        $apod = app(NasaApiService::class)->getLatestApod();

        $this->assertInstanceOf(
            ApodData::class,
            $apod
        );

        $this->assertSame('video', $apod->mediaType);

        $this->assertSame(
            'https://example.com/occultation.mp4',
            $apod->sourceUrl
        );

        $this->assertSame(
            'The Mutual Occultation of Ganymede and Io',
            $apod->title
        );
    }

    public function test_gets_apod_youtube_video_successfully(): void
    {
        Http::fake([
            'science.nasa.gov/*' => Http::response([
                [
                    'date' => '2026-10-18',
                    'title' => 'Sonified: Eagle Nebula Pillars',
                    'explanation' => 'A video about the Eagle Nebula.',
                    'media_type' => 'video',
                    'url' => 'https://science.nasa.gov/image-article/example/',
                    'hdurl' => 'https://example.com/preview.jpg',
                    'basic_html' => '<iframe src="//www.youtube.com/embed/DzrCEm1ZBRY?rel=0"></iframe>',
                    'copyright' => 'NASA',
                ],
            ], 200),
        ]);

        $apod = app(NasaApiService::class)->getLatestApod();

        $this->assertInstanceOf(
            ApodData::class,
            $apod
        );

        $this->assertSame('video', $apod->mediaType);

        $this->assertSame(
            'https://www.youtube.com/embed/DzrCEm1ZBRY?rel=0',
            $apod->sourceUrl
        );
    }

    public function test_accepts_hyperbolic_orbital_data(): void
    {
        $orbit = OrbitalData::fromArray([
            'eccentricity' => '1.25',
            'inclination' => '45.5',
            'semi_major_axis' => '-2.5',
            'orbital_period' => null,
            'orbit_class' => [
                'orbit_class_type' => 'HYP',
            ],
        ]);

        $this->assertEqualsWithDelta(
            1.25,
            $orbit->eccentricity,
            0.000001
        );

        $this->assertEqualsWithDelta(
            -2.5,
            $orbit->semiMajorAxis,
            0.000001
        );

        $this->assertNull($orbit->orbitalPeriod);
        $this->assertSame('HYP', $orbit->orbitClass);
    }

    public function test_rejects_invalid_hyperbolic_orbital_data(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OrbitalData::fromArray([
            'eccentricity' => '1.25',
            'inclination' => '45.5',
            'semi_major_axis' => '2.5',
            'orbital_period' => null,
            'orbit_class' => [
                'orbit_class_type' => 'HYP',
            ],
        ]);
    }

    public function test_rejects_invalid_close_approach_list(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response([
                'near_earth_objects' => [
                    '2026-11-01' => [
                        [
                            'id' => '2307493',
                            'name' => '307493 (2002 XP90)',
                            'estimated_diameter' => [
                                'meters' => [
                                    'estimated_diameter_min' => 513.52,
                                    'estimated_diameter_max' => 1148.26,
                                ],
                            ],
                            'is_potentially_hazardous_asteroid' => false,
                            'close_approach_data' => 'invalid',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid NASA close approach list.'
        );

        app(NasaApiService::class)->getAsteroids(
            '2026-11-01',
            '2026-11-01'
        );
    }

    public function test_accepts_empty_close_approach_list(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response([
                'near_earth_objects' => [
                    '2026-11-01' => [
                        [
                            'id' => '2307493',
                            'name' => '307493 (2002 XP90)',
                            'estimated_diameter' => [
                                'meters' => [
                                    'estimated_diameter_min' => 513.52,
                                    'estimated_diameter_max' => 1148.26,
                                ],
                            ],
                            'is_potentially_hazardous_asteroid' => false,
                            'close_approach_data' => [],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(NasaApiService::class)->getAsteroids(
            '2026-11-01',
            '2026-11-01'
        );

        $this->assertSame([], $result);
    }

    public function test_rejects_missing_close_approach_data(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response([
                'near_earth_objects' => [
                    '2026-11-01' => [
                        [
                            'id' => '2307493',
                            'name' => '307493 (2002 XP90)',
                            'estimated_diameter' => [
                                'meters' => [
                                    'estimated_diameter_min' => 513.52,
                                    'estimated_diameter_max' => 1148.26,
                                ],
                            ],
                            'is_potentially_hazardous_asteroid' => false,
                            // close_approach_data навмисно відсутнє
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Missing NASA close approach data.'
        );

        app(NasaApiService::class)->getAsteroids(
            '2026-11-01',
            '2026-11-01'
        );
    }

    public function test_rejects_negative_asteroid_diameter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AsteroidData::fromArray([
            'id' => '2307493',
            'name' => 'Test Asteroid',
            'estimated_diameter' => [
                'meters' => [
                    'estimated_diameter_min' => -100,
                    'estimated_diameter_max' => 500,
                ],
            ],
            'is_potentially_hazardous_asteroid' => false,
        ]);
    }

    public function test_rejects_reversed_asteroid_diameter_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AsteroidData::fromArray([
            'id' => '2307493',
            'name' => 'Test Asteroid',
            'estimated_diameter' => [
                'meters' => [
                    'estimated_diameter_min' => 1000,
                    'estimated_diameter_max' => 500,
                ],
            ],
            'is_potentially_hazardous_asteroid' => false,
        ]);
    }

    public function test_rejects_nonexistent_close_approach_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid NASA close approach date.'
        );

        CloseApproachData::fromArray([
            'close_approach_date' => '2026-02-31',
            'epoch_date_close_approach' => 1772496000000,
            'relative_velocity' => [
                'kilometers_per_hour' => '50000',
            ],
            'miss_distance' => [
                'kilometers' => '1000000',
            ],
            'orbiting_body' => 'Earth',
        ]);
    }

    public function test_rejects_apod_image_without_hdurl(): void
    {
        Http::fake([
            'science.nasa.gov/*' => Http::response([
                [
                    'date' => '2026-11-01',
                    'title' => 'Test NASA Image',
                    'explanation' => 'A test image.',
                    'media_type' => 'image',
                    'url' => 'https://science.nasa.gov/image-article/example/',
                    'copyright' => 'NASA',
                ],
            ], 200),
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(NasaApiService::class)->getLatestApod();
    }

    public function test_rejects_apod_video_without_media_source(): void
    {
        Http::fake([
            'science.nasa.gov/*' => Http::response([
                [
                    'date' => '2026-10-28',
                    'title' => 'NASA Video Without Source',
                    'explanation' => 'A video without an available source.',
                    'media_type' => 'video',
                    'url' => 'https://science.nasa.gov/image-article/example/',
                    'hdurl' => 'https://example.com/preview.jpg',
                    'basic_html' => '<html><body><p>No video available.</p></body></html>',
                    'copyright' => 'NASA',
                ],
            ], 200),
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(NasaApiService::class)->getLatestApod();
    }

    public function test_gets_asteroid_close_approach_successfully(): void
    {
        Http::fake([
            'api.nasa.gov/*' => Http::response([
                'near_earth_objects' => [
                    '2026-11-01' => [
                        [
                            'id' => '2307493',
                            'name' => '307493 (2002 XP90)',
                            'estimated_diameter' => [
                                'meters' => [
                                    'estimated_diameter_min' => 513.52,
                                    'estimated_diameter_max' => 1148.26,
                                ],
                            ],
                            'is_potentially_hazardous_asteroid' => false,
                            'close_approach_data' => [
                                [
                                    'close_approach_date' => '2026-11-01',
                                    'epoch_date_close_approach' => 1793492400000,
                                    'relative_velocity' => [
                                        'kilometers_per_hour' => '57938.91',
                                    ],
                                    'miss_distance' => [
                                        'kilometers' => '45865643.87',
                                    ],
                                    'orbiting_body' => 'Earth',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(NasaApiService::class)->getAsteroids(
            '2026-11-01',
            '2026-11-01'
        );

        $this->assertCount(1, $result);

        $this->assertInstanceOf(
            AsteroidData::class,
            $result[0]['asteroid']
        );

        $this->assertInstanceOf(
            CloseApproachData::class,
            $result[0]['approach']
        );

        $this->assertSame(
            '2307493',
            $result[0]['asteroid']->nasaReferenceId
        );

        $this->assertSame(
            '2026-11-01',
            $result[0]['approach']->date
        );

        $this->assertEqualsWithDelta(
            57938.91,
            $result[0]['approach']->velocityKmh,
            0.01
        );

        $this->assertEqualsWithDelta(
            45865643.87,
            $result[0]['approach']->distanceKm,
            0.01
        );

        $this->assertSame(
            'Earth',
            $result[0]['approach']->orbitingBody
        );
    }

    public function test_accepts_elliptical_orbital_data(): void
    {
        $orbit = OrbitalData::fromArray([
            'eccentricity' => '0.5857299389',
            'inclination' => '23.1151895885',
            'semi_major_axis' => '2.0796148112',
            'orbital_period' => '1095.4001086',
            'orbit_class' => [
                'orbit_class_type' => 'APO',
            ],
        ]);

        $this->assertEqualsWithDelta(
            0.5857299389,
            $orbit->eccentricity,
            0.000001
        );

        $this->assertEqualsWithDelta(
            2.0796148112,
            $orbit->semiMajorAxis,
            0.000001
        );

        $this->assertEqualsWithDelta(
            1095.4001086,
            $orbit->orbitalPeriod,
            0.000001
        );

        $this->assertSame('APO', $orbit->orbitClass);
    }

    public function test_apod_image_uses_regular_url_when_hdurl_is_missing(): void
    {
        $apod = ApodData::fromArray([
            'date' => '2026-11-13',
            'title' => 'Galaxy',
            'explanation' => 'A distant galaxy.',
            'media_type' => 'image',
            'url' => 'https://apod.nasa.gov/apod/image/2611/galaxy.jpg',
        ]);

        $this->assertSame(
            'https://apod.nasa.gov/apod/image/2611/galaxy.jpg',
            $apod->sourceUrl
        );

        $this->assertSame('image', $apod->mediaType);
    }

    public function test_apod_video_accepts_direct_youtube_url(): void
    {
        $apod = ApodData::fromArray([
            'date' => '2026-11-13',
            'title' => 'Space video',
            'explanation' => 'An astronomy video.',
            'media_type' => 'video',
            'url' => 'https://www.youtube.com/watch?v=example123',
        ]);

        $this->assertSame(
            'https://www.youtube.com/watch?v=example123',
            $apod->sourceUrl
        );

        $this->assertSame('video', $apod->mediaType);
    }

    public function test_gets_apod_for_specific_date(): void
    {
        Http::fake([
            'science.nasa.gov/*' => Http::response([
                'date' => '2026-11-13',
                'title' => 'The Milky Way',
                'explanation' => 'A view of our galaxy.',
                'media_type' => 'image',
                'hdurl' => 'https://apod.nasa.gov/apod/image/2611/galaxy.jpg',
            ], 200),
        ]);

        $apod = app(NasaApiService::class)
            ->getApod('2026-11-13');

        $this->assertSame('2026-11-13', $apod->date);
        $this->assertSame('The Milky Way', $apod->title);
        $this->assertSame('image', $apod->mediaType);

        Http::assertSent(
            fn ($request) => str_contains(
                $request->url(),
                '/wp-json/wp/v2/apod-basic/261113'
            )
        );
    }

    public function test_get_apod_rejects_invalid_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(NasaApiService::class)
            ->getApod('2026-02-30');
    }

    public function test_get_apod_handles_server_error(): void
    {
        Http::fake([
            'science.nasa.gov/*' => Http::response(
                [],
                500
            ),
        ]);

        $this->expectException(\RuntimeException::class);

        app(NasaApiService::class)
            ->getApod('2026-11-13');
    }
}
