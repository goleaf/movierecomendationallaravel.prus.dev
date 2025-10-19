<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxyImageTest extends TestCase
{
    public function test_signed_request_streams_external_artwork(): void
    {
        Http::fake([
            'https://images.test/posters/proxied.jpg' => Http::response('poster-bytes', 200, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'max-age=60, public',
                'Content-Length' => (string) strlen('poster-bytes'),
                'Last-Modified' => 'Wed, 01 Jan 2025 12:00:00 GMT',
            ]),
        ]);

        $signedUrl = artwork_url('https://images.test/posters/proxied.jpg');
        $this->assertNotNull($signedUrl, 'Expected artwork_url helper to return a signed URL.');

        $response = $this->get($signedUrl);

        $response->assertOk();
        $this->assertSame('poster-bytes', $response->getContent());
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('Cache-Control', 'max-age=60, public');
        $response->assertHeader('Content-Length', (string) strlen('poster-bytes'));
        $response->assertHeader('Last-Modified', 'Wed, 01 Jan 2025 12:00:00 GMT');

        Http::assertSentCount(1);
    }

    public function test_unsigned_request_is_rejected(): void
    {
        $response = $this->get('/api/artwork?src='.urlencode('https://images.test/posters/proxied.jpg'));

        $response->assertForbidden();
    }
}
