<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CacheProxiedImage;
use App\Support\ImageProxyStorage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Csp\AddCspHeaders;
use Tests\TestCase;

class ImageProxyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(AddCspHeaders::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }

    public function test_unsigned_requests_are_rejected(): void
    {
        $response = $this->get('/proxy/artwork?url=test');

        $response->assertStatus(403);
    }

    public function test_serves_cached_image_with_headers(): void
    {
        Storage::fake('public');
        Carbon::setTestNow(Carbon::now());
        config(['services.artwork_proxy.ttl' => 600]);

        $url = 'https://images.example/test.png';
        $storage = app(ImageProxyStorage::class);
        $record = $storage->write($url, 'poster', 'cached-image', 'image/png');

        $signed = proxy_image_url($url, 'poster');

        $response = $this->get($signed);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=600', $cacheControl);
        $response->assertHeader('ETag', '"'.$record['metadata']['hash'].'"');
        $this->assertSame('cached-image', $response->streamedContent());
    }

    public function test_dispatches_job_and_returns_404_when_fetch_fails(): void
    {
        Storage::fake('public');
        Queue::fake();
        config(['services.artwork_proxy.ttl' => 600]);

        $url = 'https://images.example/missing.png';
        Http::fake([
            $url => Http::response('error', 500),
        ]);

        $signed = proxy_image_url($url, 'poster');

        $response = $this->get($signed);

        $response->assertStatus(404);
        Queue::assertPushedOn('images', CacheProxiedImage::class);
        $this->assertNull(app(ImageProxyStorage::class)->get($url, 'poster'));
    }
}
