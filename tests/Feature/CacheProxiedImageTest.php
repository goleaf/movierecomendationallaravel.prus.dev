<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CacheProxiedImage;
use App\Support\ImageProxyStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CacheProxiedImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('image-proxy');

        config()->set('image-proxy.disk', 'image-proxy');
        config()->set('image-proxy.directory', 'testing/proxy');
        config()->set('image-proxy.ttl', 3600);

        app()->forgetInstance(ImageProxyStorage::class);
    }

    public function test_it_fetches_and_stores_remote_artwork(): void
    {
        $url = 'https://cdn.example.com/poster.jpg?signature=abc123';

        Http::fake([
            $url => Http::response('image-bytes', 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => '11',
                'Last-Modified' => 'Wed, 21 Oct 2015 07:28:00 GMT',
                'ETag' => '"abc123"',
            ]),
        ]);

        CacheProxiedImage::dispatchSync($url);

        /** @var ImageProxyStorage $storage */
        $storage = app(ImageProxyStorage::class);

        $this->assertTrue($storage->exists($url));

        $metadata = $storage->metadata($url);
        $this->assertIsArray($metadata);
        $this->assertSame($url, $metadata['source_url']);
        $this->assertSame('image/jpeg', $metadata['content_type']);
        $this->assertSame(11, $metadata['content_length']);
        $this->assertSame('"abc123"', $metadata['etag']);
        $this->assertArrayHasKey('cached_at', $metadata);

        CacheProxiedImage::dispatchSync($url);

        Http::assertSentCount(1);
    }
}
