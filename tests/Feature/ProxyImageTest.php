<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use App\Support\AnalyticsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxyImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('csp.enabled', false);
        config()->set('database.redis.client', 'predis');

        app()->bind(AnalyticsCache::class, static function (): AnalyticsCache {
            return new class extends AnalyticsCache
            {
                public function rememberCtr(string $segment, array $parameters, \Closure $resolver): mixed
                {
                    return $resolver();
                }

                public function rememberTrends(string $segment, array $parameters, \Closure $resolver): mixed
                {
                    return $resolver();
                }

                public function rememberTrending(string $segment, array $parameters, \Closure $resolver): mixed
                {
                    return $resolver();
                }

                public function flushCtr(): void {}

                public function flushTrends(): void {}

                public function flushTrending(): void {}
            };
        });
    }

    public function test_proxy_route_streams_remote_image(): void
    {
        Http::fake([
            'images.test/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/png']),
        ]);

        $signedUrl = proxy_image_url('https://images.test/poster.png');

        $response = $this->get($signedUrl);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertSame('image-bytes', $response->getContent());
    }

    public function test_search_page_uses_proxy_image_urls(): void
    {
        $movie = Movie::factory()->create([
            'poster_url' => 'https://images.test/poster.jpg',
        ]);

        $response = $this->get(route('search'));

        $response->assertOk();
        $response->assertSee('/proxy/', false);
        $response->assertDontSee($movie->poster_url, false);
    }
}
