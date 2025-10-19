<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MovieApis\OmdbClient;
use App\Services\MovieApis\RateLimitedClient;
use App\Services\MovieApis\RateLimitedClientConfig;
use App\Services\MovieApis\TmdbClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Uri;
use Tests\TestCase;

class UriBuildTest extends TestCase
{
    protected static bool $globalEnvCreated = false;

    protected bool $createdEnv = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $envPath = static::projectBasePath().'/.env';

        if (! file_exists($envPath)) {
            file_put_contents($envPath, 'APP_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL);
            static::$globalEnvCreated = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        $envPath = static::projectBasePath().'/.env';

        if (static::$globalEnvCreated && file_exists($envPath)) {
            @unlink($envPath);
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $envPath = static::projectBasePath().'/.env';

        if (! file_exists($envPath)) {
            file_put_contents($envPath, 'APP_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL);
            $this->createdEnv = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->createdEnv) {
            @unlink(static::projectBasePath().'/.env');
        }

        parent::tearDown();
    }

    protected static function projectBasePath(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_tmdb_find_request_encodes_path_and_merges_query(): void
    {
        $config = new RateLimitedClientConfig(
            baseUrl: 'https://api.example.com/3/',
            timeout: 5.0,
            retry: [],
            backoff: [],
            rateLimit: ['window' => 60, 'allowance' => 60],
            defaultQuery: ['api_key' => 'abc123'],
            defaultHeaders: [],
            rateLimiterKey: 'tmdb:test',
        );

        RateLimiter::clear('tmdb:test');

        $client = new TestRateLimitedClient($this->httpFactory(), $config);
        $tmdb = new TmdbClient($client, 'en-US', ['en-US', 'fr-FR']);

        $tmdb->findByImdbId('tt 123', 'en-US');

        $request = $client->lastRequest();

        $this->assertNotNull($request);
        $this->assertSame('GET', $request['method']);
        $this->assertSame('find/tt%20123', $request['path']);
        $this->assertSame('imdb_id', $request['query']['external_source'] ?? null);
        $this->assertSame('en-US', $request['query']['language'] ?? null);
    }

    public function test_tmdb_fetch_title_builds_tv_path_and_handles_additional_query(): void
    {
        $config = new RateLimitedClientConfig(
            baseUrl: 'https://api.example.com/3/',
            timeout: 5.0,
            retry: [],
            backoff: [],
            rateLimit: ['window' => 60, 'allowance' => 60],
            defaultQuery: ['api_key' => 'abc123'],
            defaultHeaders: [],
            rateLimiterKey: 'tmdb:test2',
        );

        RateLimiter::clear('tmdb:test2');

        $client = new TestRateLimitedClient($this->httpFactory(), $config);
        $tmdb = new TmdbClient($client, 'en-US', ['en-US', 'fr-FR']);

        $tmdb->fetchTitle(42, 'fr-FR', 'tv', [
            'append_to_response' => 'images, credits',
        ]);

        $request = $client->lastRequest();

        $this->assertNotNull($request);
        $this->assertSame('tv/42', $request['path']);
        $this->assertSame('fr-FR', $request['query']['language'] ?? null);
        $this->assertSame('images, credits', $request['query']['append_to_response'] ?? null);
    }

    public function test_omdb_search_merges_default_parameters_and_encodes_terms(): void
    {
        $config = new RateLimitedClientConfig(
            baseUrl: 'https://www.omdbapi.com/',
            timeout: 5.0,
            retry: [],
            backoff: [],
            rateLimit: ['window' => 60, 'allowance' => 60],
            defaultQuery: ['apikey' => 'secret'],
            defaultHeaders: [],
            rateLimiterKey: 'omdb:test',
        );

        RateLimiter::clear('omdb:test');

        $client = new TestRateLimitedClient($this->httpFactory(), $config);
        $omdb = new OmdbClient($client, ['type' => 'movie']);

        $omdb->search('Star Wars', ['page' => 2]);

        $request = $client->lastRequest();

        $this->assertNotNull($request);
        $this->assertSame('', $request['path']);
        $this->assertSame('Star Wars', $request['query']['s'] ?? null);
        $this->assertSame('movie', $request['query']['type'] ?? null);
        $this->assertSame('2', $request['query']['page'] ?? null);
    }

    protected function httpFactory(): HttpFactory
    {
        return app(HttpFactory::class);
    }
}

class TestRateLimitedClient extends RateLimitedClient
{
    /**
     * @var array<int, array{method: string, path: string, query: array<string, mixed>, options: array<string, mixed>}>
     */
    public array $recorded = [];

    protected function performRequest(string $method, Uri|string $path, array $query = [], array $options = []): array
    {
        [$resolvedPath, $resolvedQuery] = $this->prepareUri($path, $query, $options);

        $this->recorded[] = [
            'method' => $method,
            'path' => $resolvedPath,
            'query' => $resolvedQuery,
            'options' => $options,
        ];

        return [];
    }

    /**
     * @return array{method: string, path: string, query: array<string, mixed>, options: array<string, mixed>}|null
     */
    public function lastRequest(): ?array
    {
        if ($this->recorded === []) {
            return null;
        }

        return $this->recorded[array_key_last($this->recorded)];
    }
}
