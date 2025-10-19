<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CacheProxyImage;
use App\Support\ImageProxyStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::disk('public')->deleteDirectory(ImageProxyStorage::DIRECTORY);
        Storage::disk('local')->deleteDirectory(ImageProxyStorage::DIRECTORY);
        config()->set('csp.enabled', false);
        config()->set('queue.default', 'sync');
        config()->set('app.key', 'base64:'.base64_encode('0123456789abcdef0123456789abcdef'));
    }

    public function test_unsigned_requests_are_rejected(): void
    {
        $storage = app(ImageProxyStorage::class);
        $cacheKey = $storage->cacheKeyFor('https://example.com/poster.jpg');

        $response = $this->get("/proxy/artwork/{$cacheKey}?source=https://example.com/poster.jpg");

        $response->assertForbidden();
    }

    public function test_signed_request_returns_cached_file(): void
    {
        Http::fake([
            'https://example.com/poster.jpg' => Http::response('image-bytes', 200, [
                'Content-Type' => 'image/jpeg',
                'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
            ]),
        ]);

        $storage = app(ImageProxyStorage::class);
        $url = 'https://example.com/poster.jpg';
        $normalized = $storage->normalizeUrl($url);
        $cacheKey = $storage->cacheKeyFor($normalized);

        $signedUrl = proxy_image_url($url);

        $metadataBefore = $storage->metadata($cacheKey);
        $this->assertNotNull($metadataBefore);
        $path = $storage->pathForCacheKey($cacheKey, 'jpg');
        $this->assertTrue($storage->filesystem()->exists($path));
        $this->assertTrue(Storage::disk('public')->exists($path));

        $response = $this->get($signedUrl);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame('image-bytes', $response->streamedContent());

        $path = $storage->pathForCacheKey($cacheKey, 'jpg');
        $this->assertTrue($storage->filesystem()->exists($path));
    }

    public function test_cache_job_stores_image_and_metadata(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response('png-data', 200, [
                'Content-Type' => 'image/png',
                'Last-Modified' => 'Tue, 20 Oct 2015 07:28:00 GMT',
            ]),
        ]);

        $storage = app(ImageProxyStorage::class);
        $url = 'https://example.com/image.png?b=2&a=1';
        $cacheKey = $storage->cacheKeyFor($storage->normalizeUrl($url));

        $job = new CacheProxyImage($url, $cacheKey);
        $job->handle($storage);

        $metadata = $storage->metadata($cacheKey);
        $this->assertNotNull($metadata);
        $this->assertSame('image/png', $metadata['content_type']);
        $this->assertSame('png', $metadata['extension']);
        $this->assertSame('Tue, 20 Oct 2015 07:28:00 GMT', $metadata['last_modified']);

        $path = $storage->pathForCacheKey($cacheKey, 'png');
        $this->assertTrue($storage->filesystem()->exists($path));
    }
}
