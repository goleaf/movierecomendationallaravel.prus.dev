<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MovieApis\RateLimitedClient;
use App\Services\MovieApis\RateLimitedClientConfig;
use App\Services\MovieApis\TmdbClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class TmdbBatchTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_batch_fetches_tmdb_metadata_with_retries(): void
    {
        Http::fake([
            'https://api.themoviedb.org/3/find/tt0111161*' => Http::sequence()
                ->push([], 500)
                ->push(['movie_results' => [['id' => 1]]], 200),
            'https://api.themoviedb.org/3/find/tt0068646*' => Http::response(['movie_results' => [['id' => 2]]], 200),
            'https://api.themoviedb.org/3/movie/550*' => Http::response(['id' => 550], 200),
            'https://api.themoviedb.org/3/tv/42*' => Http::sequence()
                ->push([], 503)
                ->push(['id' => 42], 200),
        ]);

        RateLimiter::shouldReceive('tooManyAttempts')
            ->times(6)
            ->with('tmdb:test', 8)
            ->andReturnFalse();

        RateLimiter::shouldReceive('hit')
            ->times(6)
            ->with('tmdb:test', 30);

        $tmdb = $this->makeClient();

        $findResults = $tmdb->batchFindByImdbIds([
            ['imdb_id' => 'tt0111161', 'key' => 'shawshank'],
            'tt0068646',
        ], 'en-US');

        $this->assertSame(['movie_results' => [['id' => 1]]], $findResults['shawshank']);
        $this->assertSame(['movie_results' => [['id' => 2]]], $findResults['tt0068646']);

        $titleResults = $tmdb->batchFetchTitles([
            ['id' => 550, 'media_type' => 'movie', 'language' => 'en-US'],
            ['id' => 42, 'media_type' => 'tv', 'language' => 'en-US', 'key' => 'tv:42'],
        ]);

        $this->assertSame(['id' => 550], $titleResults['movie:550:en-US']);
        $this->assertSame(['id' => 42], $titleResults['tv:42']);

        Http::assertSentCount(6);
    }

    private function makeClient(): TmdbClient
    {
        $config = new RateLimitedClientConfig(
            baseUrl: 'https://api.themoviedb.org/3/',
            timeout: 5.0,
            retry: ['attempts' => 2, 'delay_ms' => 5, 'jitter_ms' => 0],
            backoff: ['multiplier' => 2.0, 'max_delay_ms' => 50],
            rateLimit: ['window' => 30, 'allowance' => 8],
            defaultQuery: [],
            defaultHeaders: [],
            rateLimiterKey: 'tmdb:test',
            concurrency: 2,
            retryJitterMs: 0,
            serviceName: 'tmdb-test',
        );

        $client = new RateLimitedClient(app(HttpFactory::class), $config);

        return new TmdbClient($client, 'en-US', ['en-US', 'pt-BR']);
    }
}
