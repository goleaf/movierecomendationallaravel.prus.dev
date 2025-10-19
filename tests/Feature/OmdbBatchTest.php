<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MovieApis\OmdbClient;
use App\Services\MovieApis\RateLimitedClient;
use App\Services\MovieApis\RateLimitedClientConfig;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class OmdbBatchTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_batch_fetches_omdb_cards_and_aggregates_results(): void
    {
        Http::fake([
            'https://www.omdbapi.com/*tt0109830*' => Http::sequence()
                ->push(['Response' => 'False'], 500)
                ->push(['Response' => 'True', 'Title' => 'Forrest Gump'], 200),
            'https://www.omdbapi.com/*tt1375666*' => Http::response(['Response' => 'True', 'Title' => 'Inception'], 200),
            'https://www.omdbapi.com/*t=Oppenheimer*' => Http::response(['Response' => 'True', 'Title' => 'Oppenheimer'], 200),
        ]);

        RateLimiter::shouldReceive('tooManyAttempts')
            ->times(4)
            ->with('omdb:test', 5)
            ->andReturnFalse();

        RateLimiter::shouldReceive('hit')
            ->times(4)
            ->with('omdb:test', 15);

        $omdb = $this->makeClient();

        $findResults = $omdb->batchFindByImdbIds([
            ['imdb_id' => 'tt0109830', 'parameters' => ['type' => 'movie'], 'key' => 'forrest'],
            'tt1375666',
        ]);

        $this->assertSame('True', $findResults['forrest']['Response']);
        $this->assertSame('Inception', $findResults['tt1375666']['Title']);

        $titleResults = $omdb->batchFindByTitles([
            ['title' => 'Oppenheimer'],
        ]);

        $this->assertSame('Oppenheimer', $titleResults['Oppenheimer']['Title']);

        Http::assertSentCount(4);
    }

    private function makeClient(): OmdbClient
    {
        $config = new RateLimitedClientConfig(
            baseUrl: 'https://www.omdbapi.com/',
            timeout: 5.0,
            retry: ['attempts' => 1, 'delay_ms' => 5, 'jitter_ms' => 0],
            backoff: ['multiplier' => 2.0, 'max_delay_ms' => 20],
            rateLimit: ['window' => 15, 'allowance' => 5],
            defaultQuery: ['apikey' => 'test-key'],
            defaultHeaders: [],
            rateLimiterKey: 'omdb:test',
            concurrency: 2,
            retryJitterMs: 0,
            serviceName: 'omdb-test',
        );

        $client = new RateLimitedClient(app(HttpFactory::class), $config);

        return new OmdbClient($client, [
            'r' => 'json',
        ]);
    }
}
