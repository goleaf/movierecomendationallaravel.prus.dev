<?php

declare(strict_types=1);

namespace App\Services\MovieApis {
    use Tests\Unit\Services\MovieApis\RateLimitedClientTest;

    function usleep(int $microseconds): void
    {
        RateLimitedClientTest::recordSleep($microseconds);
    }
}

namespace Tests\Unit\Services\MovieApis {

use App\Services\MovieApis\RateLimitedClient;
use DomainException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class RateLimitedClientTest extends TestCase
{
    /**
     * @var list<int>
     */
    private static array $sleepCalls = [];

    public static function recordSleep(int $microseconds): void
    {
        self::$sleepCalls[] = $microseconds;
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::$sleepCalls = [];
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_request_executes_successfully(): void
    {
        $response = Mockery::mock(Response::class, function (MockInterface $mock): void {
            $mock->shouldReceive('successful')->once()->andReturnTrue();
            $mock->shouldReceive('json')->once()->andReturn(['ok' => true]);
        });

        $request = Mockery::mock(PendingRequest::class, function (MockInterface $mock) use ($response): void {
            $mock->shouldReceive('timeout')->once()->with(5.0)->andReturnSelf();
            $mock->shouldReceive('acceptJson')->once()->andReturnSelf();
            $mock->shouldReceive('send')
                ->once()
                ->with('GET', 'resource', ['query' => ['foo' => 'bar']])
                ->andReturn($response);
        });

        $http = Mockery::mock(HttpFactory::class, function (MockInterface $mock) use ($request): void {
            $mock->shouldReceive('baseUrl')
                ->once()
                ->with('https://example.test')
                ->andReturn($request);
        });

        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturnUsing(function (string $key, int $max, callable $callback, int $decay) {
                $this->assertSame('movie-apis:' . md5('https://example.test'), $key);
                $this->assertSame(60, $max);
                $this->assertSame(60, $decay);

                $callback();

                return true;
            });

        $client = new RateLimitedClient($http, 'https://example.test', 5.0);

        $result = $client->request('GET', 'resource', ['foo' => 'bar']);

        $this->assertSame(['ok' => true], $result);
        $this->assertSame([], self::$sleepCalls);
    }

    public function test_request_retries_with_exponential_backoff(): void
    {
        $responseFailureOne = Mockery::mock(Response::class, function (MockInterface $mock): void {
            $mock->shouldReceive('successful')->once()->andReturnFalse();
            $mock->shouldReceive('serverError')->once()->andReturnTrue();
            $mock->shouldReceive('tooManyRequests')->andReturnFalse();
        });

        $responseFailureTwo = Mockery::mock(Response::class, function (MockInterface $mock): void {
            $mock->shouldReceive('successful')->once()->andReturnFalse();
            $mock->shouldReceive('serverError')->once()->andReturnTrue();
            $mock->shouldReceive('tooManyRequests')->andReturnFalse();
        });

        $responseSuccess = Mockery::mock(Response::class, function (MockInterface $mock): void {
            $mock->shouldReceive('successful')->once()->andReturnTrue();
            $mock->shouldReceive('json')->once()->andReturn(['ok' => true]);
        });

        $request = Mockery::mock(PendingRequest::class, function (MockInterface $mock) use ($responseFailureOne, $responseFailureTwo, $responseSuccess): void {
            $mock->shouldReceive('timeout')->times(3)->with(5.0)->andReturnSelf();
            $mock->shouldReceive('acceptJson')->times(3)->andReturnSelf();
            $mock->shouldReceive('send')
                ->times(3)
                ->with('GET', 'resource', ['query' => ['foo' => 'bar']])
                ->andReturn($responseFailureOne, $responseFailureTwo, $responseSuccess);
        });

        $http = Mockery::mock(HttpFactory::class, function (MockInterface $mock) use ($request): void {
            $mock->shouldReceive('baseUrl')
                ->times(3)
                ->with('https://example.test')
                ->andReturn($request);
        });

        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturnUsing(function (string $key, int $max, callable $callback, int $decay) {
                $callback();

                return true;
            });

        $client = new RateLimitedClient(
            $http,
            'https://example.test',
            5.0,
            retry: ['attempts' => 2, 'delay_ms' => 100],
            backoff: ['multiplier' => 2, 'max_delay_ms' => 250],
        );

        $result = $client->request('GET', 'resource', ['foo' => 'bar']);

        $this->assertSame(['ok' => true], $result);
        $this->assertSame([100000, 200000], self::$sleepCalls);
    }

    public function test_request_throws_when_retries_exhausted(): void
    {
        $request = Mockery::mock(PendingRequest::class, function (MockInterface $mock): void {
            $mock->shouldReceive('timeout')->times(2)->with(5.0)->andReturnSelf();
            $mock->shouldReceive('acceptJson')->times(2)->andReturnSelf();
            $mock->shouldReceive('send')
                ->twice()
                ->with('GET', 'resource', ['query' => ['foo' => 'bar']])
                ->andThrow(new DomainException('API unavailable'));
        });

        $http = Mockery::mock(HttpFactory::class, function (MockInterface $mock) use ($request): void {
            $mock->shouldReceive('baseUrl')
                ->times(2)
                ->with('https://example.test')
                ->andReturn($request);
        });

        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturnUsing(function (string $key, int $max, callable $callback, int $decay) {
                $callback();

                return true;
            });

        $client = new RateLimitedClient(
            $http,
            'https://example.test',
            5.0,
            retry: ['attempts' => 1, 'delay_ms' => 0],
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('API unavailable');

        $client->request('GET', 'resource', ['foo' => 'bar']);
    }

    public function test_request_throws_when_rate_limiter_rejects(): void
    {
        $http = Mockery::mock(HttpFactory::class);
        $http->shouldNotReceive('baseUrl');

        RateLimiter::shouldReceive('attempt')
            ->once()
            ->andReturn(false);

        $client = new RateLimitedClient($http, 'https://example.test', 5.0);

        $this->expectException(TooManyRequestsHttpException::class);

        $client->request('GET', 'resource', ['foo' => 'bar']);
    }
}

}
