<?php

namespace Tests\Feature\Api;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProxyImageEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_movie_show_returns_proxy_urls(): void
    {
        CarbonImmutable::setTestNow('2025-02-01 00:00:00');
        Carbon::setTestNow('2025-02-01 00:00:00');

        try {
            $movie = Movie::factory()->create([
                'poster_url' => 'https://example.com/images/poster.jpg',
                'backdrop_url' => 'https://example.com/images/backdrop.jpg',
            ]);

            $response = $this->getJson('/api/movies/'.$movie->getRouteKey());

            $response
                ->assertOk()
                ->assertJsonPath('data.poster_url', proxy_image_url('https://example.com/images/poster.jpg'))
                ->assertJsonPath('data.backdrop_url', proxy_image_url('https://example.com/images/backdrop.jpg'));
        } finally {
            CarbonImmutable::setTestNow();
            Carbon::setTestNow();
        }
    }

    public function test_trends_endpoint_returns_proxy_urls(): void
    {
        CarbonImmutable::setTestNow('2025-03-01 00:00:00');
        Carbon::setTestNow('2025-03-01 00:00:00');

        try {
            $movie = Movie::factory()->create([
                'title' => 'Trending Film',
                'poster_url' => 'https://example.com/posters/trending.jpg',
            ]);

            $response = $this->getJson('/api/trends?days=7');

            $response
                ->assertOk()
                ->assertJsonCount(1, 'items')
                ->assertJsonPath('items.0.id', $movie->id)
                ->assertJsonPath('items.0.poster_url', proxy_image_url('https://example.com/posters/trending.jpg'));
        } finally {
            CarbonImmutable::setTestNow();
            Carbon::setTestNow();
        }
    }
}
