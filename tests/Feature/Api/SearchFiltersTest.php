<?php

namespace Tests\Feature\Api;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('database.redis.client', 'predis');
        config()->set('cache.stores.redis', ['driver' => 'array']);

        $this->withoutMiddleware(\Spatie\Csp\AddCspHeaders::class);
    }

    public function test_it_filters_search_results_by_type_genre_and_year(): void
    {
        $matching = Movie::factory()->create([
            'title' => 'Matching Movie',
            'type' => 'movie',
            'year' => 2020,
            'genres' => ['drama', 'comedy'],
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
            ])
            ->assertJsonPath('data.0.poster_url', proxy_image_url($matching->poster_url, 'poster'));
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
