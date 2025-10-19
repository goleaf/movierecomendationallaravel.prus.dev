<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MovieApis;

use App\Services\MovieApis\Exceptions\MovieApiRateLimitException;
use App\Services\MovieApis\Exceptions\MovieApiRetryException;
use App\Services\MovieApis\Exceptions\MovieApiTransportException;
use App\Services\MovieApis\RateLimitedClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RateLimitedClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_request_throws_rate_limit_exception_when_throttled(): void
    {
        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturn(false);

        $client = new RateLimitedClient(new HttpFactory, 'https://example.test', 1.0);

        $this->expectException(MovieApiRateLimitException::class);
        $this->expectExceptionMessageMatches('/^Rate limit exceeded for movie-apis:/');

        $client->get('/resource');
    }

    public function test_request_wraps_transport_exceptions_when_no_retries_are_configured(): void
    {
        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturnUsing(function ($key, $maxAttempts, callable $callback, int $decay): bool {
                $callback();

                return true;
            });

        $http = Mockery::mock(HttpFactory::class);
        $request = Mockery::mock(PendingRequest::class, function (MockInterface $mock): void {
            $mock->shouldReceive('timeout')
                ->once()
                ->with(1.0)
                ->andReturnSelf();
            $mock->shouldReceive('acceptJson')
                ->once()
                ->andReturnSelf();
            $mock->shouldReceive('send')
                ->once()
                ->with('GET', 'resource', [
                    'query' => ['foo' => 'bar'],
                ])
                ->andThrow(new \RuntimeException('transport failure'));
        });

        $http->shouldReceive('baseUrl')
            ->once()
            ->with('https://example.test')
            ->andReturn($request);

        $client = new RateLimitedClient($http, 'https://example.test', 1.0);

        $this->expectException(MovieApiTransportException::class);
        $this->expectExceptionMessage('Movie API request GET /resource failed.');

        $client->get('/resource', ['foo' => 'bar']);
    }

    public function test_request_wraps_retry_failures_in_domain_exception(): void
    {
        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturnUsing(function ($key, $maxAttempts, callable $callback, int $decay): bool {
                $callback();

                return true;
            });

        $attempt = 0;

        $http = Mockery::mock(HttpFactory::class);
        $request = Mockery::mock(PendingRequest::class, function (MockInterface $mock) use (&$attempt): void {
            $mock->shouldReceive('timeout')
                ->twice()
                ->with(1.0)
                ->andReturnSelf();
            $mock->shouldReceive('acceptJson')
                ->twice()
                ->andReturnSelf();
            $mock->shouldReceive('send')
                ->twice()
                ->with('GET', 'resource', [
                    'query' => [],
                ])
                ->andReturnUsing(function () use (&$attempt): never {
                    $attempt++;

                    throw new \RuntimeException('transport failure '.$attempt);
                });
        });

        $http->shouldReceive('baseUrl')
            ->twice()
            ->with('https://example.test')
            ->andReturn($request);

        $client = new RateLimitedClient($http, 'https://example.test', 1.0, [
            'attempts' => 1,
        ]);

        $this->expectException(MovieApiRetryException::class);
        $this->expectExceptionMessage('Movie API request GET /resource failed after 2 attempts.');

        try {
            $client->get('/resource');
        } catch (MovieApiRetryException $exception) {
            $previous = $exception->getPrevious();

            $this->assertInstanceOf(\RuntimeException::class, $previous);

            if ($previous instanceof \RuntimeException) {
                $this->assertSame('transport failure 2', $previous->getMessage());
            }

            throw $exception;
        }
    }
}
