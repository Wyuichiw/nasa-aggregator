<?php

namespace Tests\Feature;

use App\Http\Requests\AsteroidFilterRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AsteroidFilterRequestTest extends TestCase
{
    private function validateFilters(array $data)
    {
        $request = new AsteroidFilterRequest;

        return Validator::make($data, $request->rules());
    }

    public function test_valid_date_range_passes(): void
    {
        $validator = $this->validateFilters([
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-15',
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $validator = $this->validateFilters([
            'date_from' => '2026-11-15',
            'date_to' => '2026-11-01',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'date_to',
            $validator->errors()->toArray()
        );
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $validator = $this->validateFilters([
            'date_from' => '15-11-2026',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'date_from',
            $validator->errors()->toArray()
        );
    }

    public function test_hazardous_filter_accepts_boolean_values(): void
    {
        foreach ([0, 1, '0', '1'] as $value) {
            $validator = $this->validateFilters([
                'hazardous' => $value,
            ]);

            $this->assertTrue($validator->passes());
        }
    }

    public function test_invalid_sort_field_is_rejected(): void
    {
        $validator = $this->validateFilters([
            'sort_by' => 'unknown_column',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'sort_by',
            $validator->errors()->toArray()
        );
    }

    public function test_per_page_cannot_exceed_100(): void
    {
        $validator = $this->validateFilters([
            'per_page' => 101,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'per_page',
            $validator->errors()->toArray()
        );
    }

    public function test_page_must_be_positive(): void
    {
        $validator = $this->validateFilters([
            'page' => 0,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'page',
            $validator->errors()->toArray()
        );
    }

    public function test_all_valid_filters_pass_together(): void
    {
        $validator = $this->validateFilters([
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-15',
            'hazardous' => '1',
            'sort_by' => 'distance',
            'sort_direction' => 'asc',
            'per_page' => 20,
            'page' => 2,
        ]);

        $this->assertTrue($validator->passes());
    }
}
