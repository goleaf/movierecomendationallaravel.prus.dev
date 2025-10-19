<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ProxyImageHelper;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Tests\TestCase;

class ProxyImageHelperTest extends TestCase
{
    public function test_normalize_url_trims_and_defaults_scheme(): void
    {
        $normalized = ProxyImageHelper::normalizeUrl('  Example.COM/images/poster.jpg  ');

        $this->assertSame('https://example.com/images/poster.jpg', $normalized);
    }

    public function test_normalize_url_rejects_invalid_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProxyImageHelper::normalizeUrl('ftp://invalid.example.com/image.png');
    }

    public function test_storage_paths_are_deterministic(): void
    {
        $normalized = ProxyImageHelper::normalizeUrl('https://example.com/path/to/poster.jpg?size=large');

        $base = ProxyImageHelper::basePath($normalized);
        $content = ProxyImageHelper::contentPath($normalized);
        $metadata = ProxyImageHelper::metadataPath($normalized);

        $this->assertStringStartsWith('proxied-images/', $base);
        $this->assertSame($base.'/image', $content);
        $this->assertSame($base.'/meta.json', $metadata);
    }

    public function test_signed_url_returns_null_when_source_is_missing(): void
    {
        $this->assertNull(ProxyImageHelper::signedUrl(null));
        $this->assertNull(ProxyImageHelper::signedUrl('   '));
    }

    public function test_signed_url_wraps_normalized_value(): void
    {
        URL::shouldReceive('signedRoute')
            ->once()
            ->with('images.proxy', ['url' => 'https://example.com/poster.jpg'])
            ->andReturn('https://app.test/images/proxy?url=https%3A%2F%2Fexample.com%2Fposter.jpg&signature=abc');

        $signed = ProxyImageHelper::signedUrl('https://example.com/poster.jpg');

        $this->assertSame('https://app.test/images/proxy?url=https%3A%2F%2Fexample.com%2Fposter.jpg&signature=abc', $signed);
    }
}
