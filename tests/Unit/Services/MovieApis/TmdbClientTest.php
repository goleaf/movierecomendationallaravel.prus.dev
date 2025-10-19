<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MovieApis;

use App\Services\MovieApis\RateLimitedClient;
use App\Services\MovieApis\TmdbClient;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TmdbClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_find_by_imdb_id_falls_back_to_default_locale_when_not_allowed(): void
    {
        $client = Mockery::mock(RateLimitedClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('batch')
                ->once()
                ->with(Mockery::on(function (array $requests): bool {
                    $this->assertCount(1, $requests);
                    $payload = $requests[0];
                    $this->assertSame('single', $payload['key']);
                    $this->assertSame('find/tt0123456', $payload['path']);
                    $this->assertSame([
                        'external_source' => 'imdb_id',
                        'language' => 'en-US',
                    ], $payload['query']);

                    return true;
                }))
                ->andReturn(['single' => ['movie_results' => []]]);
        });

        $service = new TmdbClient($client, 'en-US', ['en-US', 'pt-BR']);

        $result = $service->findByImdbId('tt0123456', 'ru-RU');

        $this->assertSame([], $result['movie_results']);
    }

    public function test_fetch_title_uses_media_type_and_merges_query_overrides(): void
    {
        $client = Mockery::mock(RateLimitedClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('batch')
                ->once()
                ->with(Mockery::on(function (array $requests): bool {
                    $this->assertCount(1, $requests);
                    $payload = $requests[0];
                    $this->assertSame('single', $payload['key']);
                    $this->assertSame('tv/42', $payload['path']);
                    $this->assertSame([
                        'append_to_response' => 'credits,images',
                        'language' => 'pt-BR',
                    ], $payload['query']);

                    return true;
                }))
                ->andReturn(['single' => ['id' => 42]]);
        });

        $service = new TmdbClient($client, 'en-US', ['en-US', 'pt-BR']);

        $result = $service->fetchTitle(42, 'pt-BR', 'tv', [
            'append_to_response' => 'credits,images',
        ]);

        $this->assertSame(42, $result['id']);
    }
}
