<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CacheProxyImage;
use App\Support\ImageProxyStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public function test_helper_generates_signed_url_and_dispatches_job_when_missing(): void
    {
        Storage::fake('public');
        Queue::fake();

        $url = 'https://image.tmdb.org/t/p/w500/sample-poster.jpg';

        $signed = proxy_image_url($url);

        $this->assertNotNull($signed);
        $this->assertStringContainsString('signature=', $signed);

        $normalized = ImageProxyStorage::normalizeUrl($url);
        $this->assertNotNull($normalized);

        Queue::assertPushed(CacheProxyImage::class, function (CacheProxyImage $job) use ($normalized): bool {
            return $job->url === $normalized && $job->force === false;
        });
    }

    public function test_proxy_route_fetches_and_streams_image_when_missing(): void
    {
        Storage::fake('public');

        $url = 'https://image.tmdb.org/t/p/original/new-poster.png';
        $normalized = ImageProxyStorage::normalizeUrl($url);
        $this->assertNotNull($normalized);

        Http::fake([
            $normalized => Http::response('img-bytes', 200, [
                'Content-Type' => 'image/png',
                'ETag' => '"etag-123"',
                'Last-Modified' => now()->toRfc7231String(),
            ]),
        ]);

        $signed = proxy_image_url($url);

        $response = $this->get($signed);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertIsString($cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=86400', $cacheControl);
        $this->assertSame('img-bytes', $response->streamedContent());

        $key = ImageProxyStorage::cacheKey($normalized);
        $metadata = ImageProxyStorage::readMetadata($key);

        $this->assertIsArray($metadata);
        $this->assertSame('image/png', $metadata['content_type']);
        $this->assertArrayHasKey('path', $metadata);
        $this->assertTrue(Storage::disk('public')->exists($metadata['path']));
    }

    public function test_proxy_route_returns_304_when_client_etag_matches(): void
    {
        Storage::fake('public');
        Queue::fake();
        Http::fake();

        $url = 'https://image.tmdb.org/t/p/w780/cached.jpg';
        $normalized = ImageProxyStorage::normalizeUrl($url);
        $this->assertNotNull($normalized);
        $key = ImageProxyStorage::cacheKey($normalized);
        $path = ImageProxyStorage::imagePath($key, 'jpg');

        ImageProxyStorage::ensureDirectory($key);
        Storage::disk('public')->put($path, 'cached-bytes');

        ImageProxyStorage::writeMetadata($key, [
            'url' => $normalized,
            'path' => $path,
            'extension' => 'jpg',
            'content_type' => 'image/jpeg',
            'content_length' => strlen('cached-bytes'),
            'etag' => 'etag-cached',
            'last_modified' => now()->subHour()->toRfc7231String(),
            'cached_at' => now()->toIso8601String(),
            'checksum' => hash('sha256', 'cached-bytes'),
        ]);

        $signed = proxy_image_url($url);

        Queue::assertNothingPushed();

        $response = $this->get($signed, [
            'If-None-Match' => '"etag-cached"',
        ]);

        $response->assertStatus(304);
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertIsString($cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=86400', $cacheControl);
    }
}
