<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

use function image_proxy_url;

class MovieResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_signed_proxy_urls_for_movie_images(): void
    {
        $now = Carbon::parse('2025-01-10 12:00:00');
        Carbon::setTestNow($now);

        $poster = 'https://img.example.com/posters/42.jpg';
        $backdrop = 'https://img.example.com/backdrops/42.jpg';

        $movie = Movie::factory()->create([
            'poster_url' => $poster,
            'backdrop_url' => $backdrop,
        ]);

        $response = $this->getJson(route('api.movies.show', $movie));

        $response->assertOk();
        $response->assertJsonPath('data.poster_url', image_proxy_url($poster));
        $response->assertJsonPath('data.backdrop_url', image_proxy_url($backdrop));

        Carbon::setTestNow();
    }
}
