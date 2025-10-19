<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CacheProxiedImage;
use App\Support\ProxyImageHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CacheProxiedImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.proxy_image_disk' => 'proxy_images']);
        Storage::fake('proxy_images');
    }

    public function test_it_caches_remote_image_and_metadata(): void
    {
        $url = 'https://cdn.example.com/posters/movie.jpg';
        $body = 'binary-image';

        Http::fake([
            $url => Http::response($body, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        (new CacheProxiedImage($url))->handle();

        $normalized = ProxyImageHelper::normalizeUrl($url);
        $contentPath = ProxyImageHelper::contentPath($normalized);
        $metadataPath = ProxyImageHelper::metadataPath($normalized);

        Storage::disk('proxy_images')->assertExists($contentPath);
        Storage::disk('proxy_images')->assertExists($metadataPath);

        $metadata = json_decode(Storage::disk('proxy_images')->get($metadataPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('image/jpeg', $metadata['mime_type']);
        $this->assertSame(strlen($body), $metadata['content_length']);
        $this->assertSame($url, $metadata['original_url']);
    }

    public function test_it_skips_non_image_responses(): void
    {
        $url = 'https://cdn.example.com/posters/invalid';

        Http::fake([
            $url => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        (new CacheProxiedImage($url))->handle();

        $normalized = ProxyImageHelper::normalizeUrl($url);
        $contentPath = ProxyImageHelper::contentPath($normalized);

        Storage::disk('proxy_images')->assertMissing($contentPath);
    }
}
