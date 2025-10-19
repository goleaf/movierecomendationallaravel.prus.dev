<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CacheProxiedImage;
use App\Support\ImageProxyStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CacheProxiedImageTest extends TestCase
{
    public function test_it_fetches_and_stores_images(): void
    {
        Storage::fake('public');

        $url = 'https://example.com/image.jpg';
        $kind = 'poster';
        $contents = 'binary-image';

        Http::fake([
            $url => Http::response($contents, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $job = new CacheProxiedImage($url, $kind);
        $job->handle(app(ImageProxyStorage::class));

        $storage = app(ImageProxyStorage::class);
        $record = $storage->get($url, $kind);

        $this->assertNotNull($record);
        $this->assertSame('image/jpeg', $record['metadata']['mime_type']);
        $this->assertSame(hash('sha256', $contents), $record['metadata']['hash']);
        Storage::disk('public')->assertExists($record['path']);
    }

    public function test_it_rejects_private_ip_urls(): void
    {
        Storage::fake('public');

        $job = new CacheProxiedImage('http://127.0.0.1/image.jpg', 'poster');

        $this->expectException(\InvalidArgumentException::class);
        $job->handle(app(ImageProxyStorage::class));
    }
}
