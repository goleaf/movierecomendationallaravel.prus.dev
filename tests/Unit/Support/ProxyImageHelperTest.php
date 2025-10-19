<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProxyImageHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function test_returns_null_for_empty_values(): void
    {
        $this->assertNull(proxy_image_url(null));
        $this->assertNull(proxy_image_url(''));
    }

    public function test_generates_signed_url(): void
    {
        config(['services.artwork_proxy.ttl' => 300]);
        Carbon::setTestNow(Carbon::parse('2024-01-01T00:00:00Z'));

        $url = 'https://example.com/poster.jpg';
        $signed = proxy_image_url($url, 'poster');

        $this->assertNotNull($signed);
        $request = Request::create($signed, 'GET');
        $this->assertTrue(URL::hasValidSignature($request));
        $this->assertStringContainsString(base64_encode($url), $signed);
    }
}
