<?php

namespace Tests\Feature\Api;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SearchFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_search_results_by_type_genre_and_year(): void
    {
        $matching = Movie::factory()->create([
            'title' => 'Matching Movie',
            'type' => 'movie',
            'year' => 2020,
            'genres' => ['drama', 'comedy'],
            'poster_url' => 'https://example.com/posters/matching.jpg',
        ]);

        Movie::factory()->create([
            'title' => 'Wrong Type',
            'type' => 'series',
            'year' => 2020,
            'genres' => ['drama'],
        ]);

        Movie::factory()->create([
            'title' => 'Wrong Genre',
            'type' => 'movie',
            'year' => 2020,
            'genres' => ['action'],
        ]);

        Movie::factory()->create([
            'title' => 'Wrong Year',
            'type' => 'movie',
            'year' => 2010,
            'genres' => ['drama'],
        ]);

        CarbonImmutable::setTestNow('2025-01-01 12:00:00');
        Carbon::setTestNow('2025-01-01 12:00:00');

        try {
            $response = $this->getJson(route('api.search', [
                'type' => 'movie',
                'genre' => 'drama',
                'yf' => 2019,
                'yt' => 2021,
            ]));

            $response
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonFragment([
                    'id' => $matching->id,
                    'title' => 'Matching Movie',
                    'type' => 'movie',
                    'poster_url' => proxy_image_url('https://example.com/posters/matching.jpg'),
                ]);
        } finally {
            CarbonImmutable::setTestNow();
            Carbon::setTestNow();
        }
    }

    public function test_it_handles_inverted_year_filters(): void
    {
        $matching = Movie::factory()->create([
            'title' => 'Period Drama',
            'type' => 'movie',
            'year' => 2005,
            'genres' => ['drama'],
        ]);

        Movie::factory()->create([
            'title' => 'Outside Range',
            'type' => 'movie',
            'year' => 1995,
            'genres' => ['drama'],
        ]);

        $response = $this->getJson(route('api.search', [
            'type' => 'movie',
            'genre' => 'drama',
            'yf' => 2010,
            'yt' => 2000,
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $matching->id,
                'title' => 'Period Drama',
            ]);
    }
}
