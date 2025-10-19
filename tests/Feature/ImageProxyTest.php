<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ImageProxyStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageProxyTest extends TestCase
{
    public function test_it_caches_and_serves_remote_images_via_signed_route(): void
    {
        Storage::fake('public');

        $payload = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=');

        Http::fake([
            'https://images.example.com/poster.png' => Http::response($payload, 200, ['Content-Type' => 'image/png']),
        ]);

        $route = proxy_image_url('https://images.example.com/poster.png');

        $response = $this->get($route);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('Cache-Control', 'max-age=604800, public');

        $normalized = ImageProxyStorage::normalizeUrl('https://images.example.com/poster.png');
        $this->assertNotNull($normalized);

        $path = ImageProxyStorage::relativePath($normalized);

        Storage::disk('public')->assertExists($path);
        $this->assertSame($payload, Storage::disk('public')->get($path));
    }

    public function test_refreshing_a_proxy_image_replaces_cached_contents(): void
    {
        Storage::fake('public');

        $call = 0;

        Http::fake(function () use (&$call) {
            $call++;

            return Http::response(
                $call === 1 ? 'first' : 'second',
                200,
                ['Content-Type' => 'image/png'],
            );
        });

        $url = '//cdn.example.com/poster.png';

        $initialRoute = proxy_image_url($url);
        $this->get($initialRoute)->assertOk();

        $normalized = ImageProxyStorage::normalizeUrl($url);
        $this->assertNotNull($normalized);
        $path = ImageProxyStorage::relativePath($normalized);

        $this->assertSame('first', Storage::disk('public')->get($path));

        $refreshRoute = proxy_image_url($url, true);
        $this->get($refreshRoute)->assertOk();

        $this->assertSame('second', Storage::disk('public')->get($path));
    }

    public function test_invalid_proxy_requests_are_rejected(): void
    {
        Http::fake();

        $route = proxy_image_url('https://images.example.com/poster.jpg');

        $tampered = preg_replace('/hash=[^&]+/', 'hash=not-valid', $route);

        $this->assertIsString($tampered);
        $this->assertNotSame($route, $tampered);

        $this->get($tampered)->assertForbidden();
    }
}
