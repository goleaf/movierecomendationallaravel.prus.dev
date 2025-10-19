<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MovieApis;

use App\Services\MovieApis\Exceptions\MovieApiRequestException;
use App\Services\MovieApis\MovieApiClientConfig;
use App\Services\MovieApis\RateLimitedClient;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Tests\TestCase;

final class RateLimitedClientTest extends TestCase
{
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiterFacade::clear('movie-api-test');
        $this->rateLimiter = $this->app->make(RateLimiter::class);
    }

    public function test_it_returns_response_when_first_attempt_succeeds(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $client = $this->makeClient();

        $response = $client->request('GET', '/movies');

        $this->assertTrue($response->successful());
        $this->assertSame(['ok' => true], $response->json());
    }

    public function test_it_retries_when_connection_exception_is_thrown(): void
    {
        $attempts = 0;
        $delays = [];

        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts < 3) {
                throw new ConnectionException('Timeout.');
            }

            return Http::response(['ok' => true], 200);
        });

        $client = $this->makeClient(
            retryLogger: function (int $attempt, int $delay, mixed $reason) use (&$delays): void {
                $delays[] = $delay;
                $this->assertInstanceOf(ConnectionException::class, $reason);
            },
            sleep: function (int $milliseconds) use (&$delays): void {
                $delays[] = $milliseconds;
            },
        );

        $response = $client->request('GET', '/movies');

        $this->assertSame(3, $attempts);
        $this->assertTrue($response->successful());
        $this->assertSame([1000, 1000, 2000, 2000], $delays);
    }

    public function test_it_honours_retry_after_header(): void
    {
        $delays = [];
        $sequence = Http::fakeSequence()
            ->pushStatus(429, headers: ['Retry-After' => '3'])
            ->push(['ok' => true], 200);

        Http::fake([
            'https://example.com/*' => $sequence,
        ]);

        $client = $this->makeClient(
            retryLogger: function (int $attempt, int $delay, mixed $reason) use (&$delays): void {
                $delays[] = $delay;
                $this->assertSame(429, $reason->status());
            },
            sleep: function (int $milliseconds) use (&$delays): void {
                $delays[] = $milliseconds;
            },
        );

        $response = $client->request('GET', '/movies');

        $this->assertTrue($response->successful());
        $this->assertSame([3000, 3000], $delays);
    }

    public function test_it_waits_until_rate_limit_allows_request(): void
    {
        $waits = [];

        Http::fake([
            'https://example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $config = new MovieApiClientConfig(
            baseUrl: 'https://example.com',
            limiterKey: 'movie-api-test',
            requestsPerInterval: 1,
            intervalSeconds: 1,
        );

        $this->rateLimiter->hit('movie-api-test', 1);

        $client = new RateLimitedClient(
            http: $this->app->make(Factory::class),
            rateLimiter: $this->rateLimiter,
            config: $config,
            throttleLogger: function (string $key, int $waitSeconds) use (&$waits): void {
                $waits[] = $waitSeconds;
            },
            sleep: function (int $milliseconds) use (&$waits): void {
                $waits[] = $milliseconds;
                RateLimiterFacade::clear('movie-api-test');
            },
        );

        $response = $client->request('GET', '/movies');

        $this->assertTrue($response->successful());
        $this->assertSame([1, 1000], $waits);
    }

    public function test_it_throws_exception_when_retries_exhausted(): void
    {
        Http::fake([
            'https://example.com/*' => Http::response(null, 503),
        ]);

        $client = $this->makeClient();

        $this->expectException(MovieApiRequestException::class);

        $client->request('GET', '/movies');
    }

    private function makeClient(?callable $retryLogger = null, ?callable $throttleLogger = null, ?callable $sleep = null): RateLimitedClient
    {
        $config = new MovieApiClientConfig(
            baseUrl: 'https://example.com',
            limiterKey: 'movie-api-test',
        );

        return new RateLimitedClient(
            http: $this->app->make(Factory::class),
            rateLimiter: $this->rateLimiter,
            config: $config,
            retryLogger: $retryLogger,
            throttleLogger: $throttleLogger,
            sleep: $sleep,
        );
    }
}
