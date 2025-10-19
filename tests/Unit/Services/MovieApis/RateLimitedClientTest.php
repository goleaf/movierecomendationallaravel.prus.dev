<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MovieApis;

use App\Services\MovieApis\RateLimitedClient;
use App\Services\MovieApis\RateLimitedClientConfig;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class RateLimitedClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_request_executes_successfully(): void
    {
        $requests = [];

        Http::fake(function (HttpRequest $request) use (&$requests) {
            $requests[] = $request->url();

            return Http::response(['ok' => true], 200);
        });

        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->with('example:rate', 10)
            ->andReturnFalse();

        RateLimiter::shouldReceive('hit')
            ->once()
            ->with('example:rate', 30);

        $client = $this->makeClient();

        $result = $client->request('GET', 'resource', ['foo' => 'bar']);

        $this->assertSame(['ok' => true], $result);
        $this->assertSame(['https://example.test/resource?foo=bar'], $requests);
    }

    public function test_request_retries_until_successful(): void
    {
        $attempts = 0;

        Http::fake(function (HttpRequest $request) use (&$attempts) {
            $attempts++;

            if ($attempts < 3) {
                return Http::response([], 500);
            }

            return Http::response(['ok' => true], 200);
        });

        RateLimiter::shouldReceive('tooManyAttempts')
            ->times(3)
            ->with('example:rate', 10)
            ->andReturnFalse();

        RateLimiter::shouldReceive('hit')
            ->times(3)
            ->with('example:rate', 30);

        $client = $this->makeClient(
            retry: ['attempts' => 2, 'delay_ms' => 10, 'jitter_ms' => 0],
            backoff: ['multiplier' => 2, 'max_delay_ms' => 20],
        );

        $result = $client->request('GET', 'resource', ['foo' => 'bar']);

        $this->assertSame(['ok' => true], $result);
        $this->assertSame(3, $attempts);
    }

    public function test_request_throws_on_failure(): void
    {
        $attempts = 0;

        Http::fake(function (HttpRequest $request) use (&$attempts) {
            $attempts++;

            return Http::response([], 500);
        });

        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->with('example:rate', 10)
            ->andReturnFalse();

        RateLimiter::shouldReceive('hit')
            ->once()
            ->with('example:rate', 30);

        $client = $this->makeClient(
            retry: ['attempts' => 0, 'delay_ms' => 0, 'jitter_ms' => 0],
        );

        $this->expectException(RequestException::class);

        try {
            $client->request('GET', 'resource', ['foo' => 'bar']);
        } finally {
            $this->assertSame(1, $attempts);
        }
    }

    public function test_request_throws_when_rate_limit_exceeded(): void
    {
        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->with('example:rate', 10)
            ->andReturnTrue();

        $client = $this->makeClient();

        $this->expectException(TooManyRequestsHttpException::class);

        $client->request('GET', 'resource', ['foo' => 'bar']);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $retry
     * @param  array<string, mixed>  $backoff
     */
    private function makeClient(array $retry = [], array $backoff = []): RateLimitedClient
    {
        $config = new RateLimitedClientConfig(
            baseUrl: 'https://example.test/',
            timeout: 5.0,
            retry: $retry,
            backoff: $backoff,
            rateLimit: ['window' => 30, 'allowance' => 10],
            defaultQuery: [],
            defaultHeaders: [],
            rateLimiterKey: 'example:rate',
            concurrency: 3,
            retryJitterMs: (int) ($retry['jitter_ms'] ?? 0),
            serviceName: 'example',
        );

        return new RateLimitedClient(app(HttpFactory::class), $config);
    }
}
