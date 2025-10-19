<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieResourceTest extends TestCase
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

    public function test_movie_resource_proxies_artwork_urls(): void
    {
        $movie = Movie::factory()->create([
            'poster_url' => 'https://example.com/posters/sample.jpg',
            'backdrop_url' => 'https://example.com/backdrops/sample.jpg',
        ]);

        $response = $this->getJson(route('api.movies.show', ['movie' => $movie]));

        $response
            ->assertOk()
            ->assertJsonPath('data.poster_url', proxy_image_url('https://example.com/posters/sample.jpg', 'poster'))
            ->assertJsonPath('data.backdrop_url', proxy_image_url('https://example.com/backdrops/sample.jpg', 'backdrop'));
    }
}
