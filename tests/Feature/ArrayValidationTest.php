<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\MovieIndexRequest;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ArrayValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->post('/test/movie-index', function (MovieIndexRequest $request) {
            return response()->json($request->validated());
        });
    }

    public function test_valid_payload_passes_validation_and_normalizes_values(): void
    {
        $payload = [
            'genres' => [' Drama ', 'romance'],
            'countries' => ['us', ' ca '],
            'filters' => [
                'runtime' => ['min' => 90, 'max' => 140],
                'release' => ['from' => '2020-01-01', 'to' => '2021-12-31'],
            ],
        ];

        $response = $this->postJson('/test/movie-index', $payload);

        $response
            ->assertOk()
            ->assertJson([
                'genres' => ['Drama', 'romance'],
                'countries' => ['US', 'CA'],
                'filters' => [
                    'runtime' => ['min' => 90, 'max' => 140],
                    'release' => ['from' => '2020-01-01', 'to' => '2021-12-31'],
                ],
            ]);
    }

    public function test_genres_must_be_an_array(): void
    {
        $response = $this->postJson('/test/movie-index', [
            'genres' => 'thriller',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('genres');

        $this->assertSame(
            'Genres must be provided as an array.',
            $response->json('errors')['genres'][0] ?? null
        );
    }

    public function test_each_genre_must_be_a_non_empty_string(): void
    {
        $response = $this->postJson('/test/movie-index', [
            'genres' => ['action', ''],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('genres.1');

        $this->assertSame(
            'Each genre must be at least 2 characters.',
            $response->json('errors')['genres.1'][0] ?? null
        );
    }

    public function test_countries_must_be_an_array_of_iso_codes(): void
    {
        $response = $this->postJson('/test/movie-index', [
            'countries' => 'USA',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('countries');

        $this->assertSame(
            'Countries must be provided as an array of ISO codes.',
            $response->json('errors')['countries'][0] ?? null
        );
    }

    public function test_country_code_must_be_two_letters(): void
    {
        $response = $this->postJson('/test/movie-index', [
            'countries' => ['United States'],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('countries.0');

        $this->assertSame(
            'Country codes must be exactly 2 letters (ISO 3166-1 alpha-2).',
            $response->json('errors')['countries.0'][0] ?? null
        );
    }

    public function test_runtime_filters_require_numeric_values(): void
    {
        $response = $this->postJson('/test/movie-index', [
            'filters' => [
                'runtime' => ['min' => 'fast', 'max' => 'slow'],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filters.runtime.min', 'filters.runtime.max']);

        $this->assertSame(
            'Runtime minimum must be a whole number of minutes.',
            $response->json('errors')['filters.runtime.min'][0] ?? null
        );
        $this->assertSame(
            'Runtime maximum must be a whole number of minutes.',
            $response->json('errors')['filters.runtime.max'][0] ?? null
        );
    }

    public function test_runtime_max_cannot_be_less_than_min(): void
    {
        $response = $this->postJson('/test/movie-index', [
            'filters' => [
                'runtime' => ['min' => 150, 'max' => 90],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('filters.runtime.max');

        $this->assertSame(
            'Runtime maximum must be greater than or equal to the minimum.',
            $response->json('errors')['filters.runtime.max'][0] ?? null
        );
    }

    public function test_release_dates_must_be_valid(): void
    {
        $response = $this->postJson('/test/movie-index', [
            'filters' => [
                'release' => ['from' => 'invalid', 'to' => '2022-01-01'],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('filters.release.from');

        $this->assertSame(
            'Release start date must be a valid date.',
            $response->json('errors')['filters.release.from'][0] ?? null
        );
    }

    public function test_release_end_date_cannot_precede_start_date(): void
    {
        $response = $this->postJson('/test/movie-index', [
            'filters' => [
                'release' => ['from' => '2023-10-01', 'to' => '2023-01-01'],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('filters.release.to');

        $this->assertSame(
            'Release end date must be on or after the start date.',
            $response->json('errors')['filters.release.to'][0] ?? null
        );
    }
}
