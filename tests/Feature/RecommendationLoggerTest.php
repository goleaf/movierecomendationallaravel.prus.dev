<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationLoggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public function test_recommendation_logging_writes_impressions_and_device_history(): void
    {
        foreach (range(1, 3) as $i) {
            Movie::create([
                'imdb_tt' => 'tt'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'title' => 'Movie '.$i,
                'type' => 'movie',
                'year' => 2020 + $i,
                'imdb_votes' => 1000 * $i,
                'imdb_rating' => 7.0 + $i / 10,
            ]);
        }

        $deviceId = 'd_test_logger';

        $this->withCookie('did', $deviceId)->get('/')->assertOk();

        $this->assertDatabaseHas('device_history', [
            'device_id' => $deviceId,
            'page' => 'home',
        ]);

        $this->assertSame(3, $this->app['db']->table('rec_ab_logs')->where('device_id', $deviceId)->where('placement', 'home')->count());
    }

    public function test_movie_show_records_click_and_device_history(): void
    {
        $movie = Movie::create([
            'imdb_tt' => 'tt9999999',
            'title' => 'Clicked Movie',
            'type' => 'movie',
            'year' => 2024,
            'imdb_votes' => 1500,
            'imdb_rating' => 8.1,
        ]);

        $deviceId = 'd_click_logger';

        $this->withCookie('did', $deviceId)
            ->get(route('movies.show', ['movie' => $movie->id, 'placement' => 'home', 'variant' => 'A']))
            ->assertOk();

        $this->assertDatabaseHas('rec_clicks', [
            'movie_id' => $movie->id,
            'device_id' => $deviceId,
            'placement' => 'home',
            'variant' => 'A',
        ]);

        $this->assertDatabaseHas('device_history', [
            'device_id' => $deviceId,
            'page' => 'movie',
            'movie_id' => $movie->id,
        ]);
    }
}
