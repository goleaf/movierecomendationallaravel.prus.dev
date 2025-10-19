<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ProxyImageHelper;
use Tests\TestCase;

final class ProxyImageHelperTest extends TestCase
{
    public function test_it_creates_deterministic_hashes(): void
    {
        $first = ProxyImageHelper::hashFor('https://example.com/image.jpg?signature=abc');
        $second = ProxyImageHelper::hashFor('https://example.com/image.jpg?signature=abc');
        $third = ProxyImageHelper::hashFor('https://example.com/image.jpg?signature=def');

        $this->assertSame($first, $second);
        $this->assertNotSame($first, $third);
        $this->assertSame(64, strlen($first));
    }

    public function test_it_generates_signed_proxy_routes(): void
    {
        $url = 'https://cdn.example.com/artwork/poster.jpg?signature=secret';

        $signed = proxy_image($url);

        $parts = parse_url($signed);
        $this->assertIsArray($parts);
        $this->assertSame('/proxy/artwork/'.ProxyImageHelper::hashFor($url), $parts['path'] ?? null);

        $query = [];
        parse_str($parts['query'] ?? '', $query);

        $this->assertArrayHasKey('source', $query);
        $this->assertArrayHasKey('signature', $query);
        $this->assertSame($url, ProxyImageHelper::decodeSource($query['source']));
    }
}
