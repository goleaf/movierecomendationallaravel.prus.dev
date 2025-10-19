<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ImageProxyStorage;
use App\Support\ProxyImageHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Csp\AddCspHeaders;
use Tests\TestCase;

final class ImageProxyControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(AddCspHeaders::class);

        Storage::fake('image-proxy');

        config()->set('image-proxy.disk', 'image-proxy');
        config()->set('image-proxy.directory', 'testing/proxy');
        config()->set('image-proxy.ttl', 3600);
        config()->set('image-proxy.headers', [
            'Cache-Control' => 'public, max-age=60, stale-while-revalidate=60',
        ]);

        app()->forgetInstance(ImageProxyStorage::class);
    }

    public function test_it_fetches_and_streams_images(): void
    {
        $url = 'https://cdn.example.com/poster.jpg?signature=abc123';

        Http::fake([
            $url => Http::response('image-data', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $signed = proxy_image($url);

        $response = $this->get($signed);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertIsString($cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=60', $cacheControl);
        $this->assertSame('image-data', $response->streamedContent());

        Http::assertSentCount(1);

        Http::fake();

        $response = $this->get($signed);

        $response->assertOk();
        $this->assertSame('image-data', $response->streamedContent());

        Http::assertSentCount(0);
    }

    public function test_it_rejects_tampered_requests(): void
    {
        $url = 'https://cdn.example.com/poster.jpg?signature=abc123';

        $signed = proxy_image($url);

        $tampered = str_replace(
            ProxyImageHelper::hashFor($url),
            str_repeat('0', 64),
            $signed
        );

        $response = $this->get($tampered);

        $response->assertForbidden();
    }
}
