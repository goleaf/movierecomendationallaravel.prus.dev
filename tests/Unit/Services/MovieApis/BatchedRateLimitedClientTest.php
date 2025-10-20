<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MovieApis;

use App\Services\MovieApis\BatchedRateLimitedClient;
use App\Services\MovieApis\RateLimitedClientConfig;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class BatchedRateLimitedClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_batch_executes_requests_and_logs_success(): void
    {
        Http::fake([
            'https://batch.test/movie/1*' => Http::response(['id' => 1]),
            'https://batch.test/movie/2*' => Http::response(['id' => 2]),
        ]);

        Log::shouldReceive('channel')->twice()->with('ingestion')->andReturnSelf();
        Log::shouldReceive('info')->twice()->withArgs(function (string $message, array $context): bool {
            $this->assertSame('movie_api.batch.trace', $message);
            $this->assertSame('tmdb', $context['service']);

            return true;
        });

        $config = new RateLimitedClientConfig(
            baseUrl: 'https://batch.test',
            timeout: 5.0,
            retry: ['attempts' => 0, 'delay_ms' => 0],
            backoff: ['multiplier' => 1, 'max_delay_ms' => 0],
            rateLimit: ['window' => 60, 'allowance' => 60],
            defaultQuery: ['api_key' => 'abc'],
            defaultHeaders: ['X-Test' => '1'],
            rateLimiterKey: 'movie-apis:'.md5('https://batch.test/'),
            batch: [
                'concurrency' => 1,
                'retry' => ['attempts' => 0, 'delay_ms' => 0],
                'headers' => ['X-Batch' => 'movies'],
            ],
        );

        RateLimiter::clear($config->rateLimiterKey());

        $client = new BatchedRateLimitedClient(Http::getFacadeRoot(), $config, 'tmdb');

        $responses = $client->batch([
            'first' => [
                'path' => 'movie/1',
                'query' => ['language' => 'en-US'],
                'headers' => ['X-Request' => 'first'],
            ],
            'second' => [
                'path' => 'movie/2',
                'query' => ['language' => 'en-US'],
            ],
        ]);

        Http::assertSentCount(2);

        $this->assertSame(1, $responses['first']['id']);
        $this->assertSame(2, $responses['second']['id']);

        Http::assertSent(function (Request $request): bool {
            $this->assertTrue($request->hasHeader('X-Batch'));
            $this->assertTrue($request->hasHeader('X-Test'));

            return true;
        });
    }

    public function test_batch_throws_on_failed_response(): void
    {
        Http::fake([
            'https://batch.test/movie/1*' => Http::response(['id' => 1]),
            'https://batch.test/movie/2*' => Http::response(['error' => true], 500),
        ]);

        Log::shouldReceive('channel')->times(2)->with('ingestion')->andReturnSelf();
        Log::shouldReceive('info')->times(2);

        $config = new RateLimitedClientConfig(
            baseUrl: 'https://batch.test',
            timeout: 5.0,
            retry: ['attempts' => 0, 'delay_ms' => 0],
            backoff: ['multiplier' => 1, 'max_delay_ms' => 0],
            rateLimit: ['window' => 60, 'allowance' => 60],
            defaultQuery: [],
            defaultHeaders: [],
        );

        RateLimiter::clear($config->rateLimiterKey());

        $client = new BatchedRateLimitedClient(Http::getFacadeRoot(), $config, 'tmdb');

        $this->expectException(
            \Illuminate\Http\Client\RequestException::class,
        );

        try {
            $client->batch([
                'first' => ['path' => 'movie/1'],
                'second' => ['path' => 'movie/2'],
            ]);
        } finally {
            Http::assertSentCount(2);
        }
    }
}
