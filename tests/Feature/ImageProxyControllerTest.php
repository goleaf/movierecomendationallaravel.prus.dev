<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ProxyImageHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageProxyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.proxy_image_disk' => 'proxy_images']);
        Storage::fake('proxy_images');
    }

    public function test_it_streams_cached_image(): void
    {
        $url = 'https://images.example.com/posters/123.jpg';
        $normalized = ProxyImageHelper::normalizeUrl($url);
        $contentPath = ProxyImageHelper::contentPath($normalized);
        $metadataPath = ProxyImageHelper::metadataPath($normalized);

        Storage::disk('proxy_images')->put($contentPath, 'cached-image-data');
        Storage::disk('proxy_images')->put($metadataPath, json_encode([
            'original_url' => $url,
            'normalized_url' => $normalized,
            'mime_type' => 'image/jpeg',
            'content_length' => 17,
            'stored_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        $response = $this->get(ProxyImageHelper::signedUrl($url));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('X-Proxy-Source', $url);
        $this->assertSame('cached-image-data', $response->streamedContent());
    }

    public function test_it_caches_when_missing(): void
    {
        $url = 'https://images.example.com/posters/456.png';
        $body = 'fresh-image';

        Http::fake([
            ProxyImageHelper::normalizeUrl($url) => Http::response($body, 200, ['Content-Type' => 'image/png']),
        ]);

        $response = $this->get(ProxyImageHelper::signedUrl($url));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertSame($body, $response->streamedContent());

        $normalized = ProxyImageHelper::normalizeUrl($url);
        Storage::disk('proxy_images')->assertExists(ProxyImageHelper::contentPath($normalized));
    }

    public function test_it_returns_not_found_when_download_fails(): void
    {
        $url = 'https://images.example.com/posters/789.jpg';

        Http::fake([
            ProxyImageHelper::normalizeUrl($url) => Http::response('error', 500),
        ]);

        $this->get(ProxyImageHelper::signedUrl($url))->assertNotFound();
    }

    public function test_it_rejects_invalid_urls(): void
    {
        $signed = url()->signedRoute('images.proxy', ['url' => 'not-a-url']);

        $this->get($signed)->assertStatus(422);
    }
}
