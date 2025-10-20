<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MovieApis;

use App\Services\MovieApis\BatchedRateLimitedClient;
use App\Services\MovieApis\OmdbClient;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OmdbClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_find_by_imdb_id_merges_optional_parameters(): void
    {
        $client = Mockery::mock(BatchedRateLimitedClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('get')
                ->once()
                ->with('/', [
                    'r' => 'json',
                    'plot' => 'full',
                    'type' => 'movie',
                    'i' => 'tt7654321',
                ])
                ->andReturn(['Response' => 'True']);
        });

        $service = new OmdbClient($client, [
            'r' => 'json',
            'plot' => 'short',
        ]);

        $result = $service->findByImdbId('tt7654321', [
            'plot' => 'full',
            'type' => 'movie',
        ]);

        $this->assertSame('True', $result['Response']);
    }

    public function test_search_filters_null_parameters_and_keeps_defaults(): void
    {
        $client = Mockery::mock(BatchedRateLimitedClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('get')
                ->once()
                ->with('/', [
                    'r' => 'json',
                    'plot' => 'short',
                    's' => 'Matrix',
                    'y' => 1999,
                ])
                ->andReturn(['Search' => []]);
        });

        $service = new OmdbClient($client, [
            'r' => 'json',
            'plot' => 'short',
        ]);

        $result = $service->search('Matrix', [
            'type' => null,
            'y' => 1999,
        ]);

        $this->assertSame([], $result['Search']);
    }

    public function test_batch_find_by_imdb_ids_merges_defaults(): void
    {
        $client = Mockery::mock(BatchedRateLimitedClient::class, function (MockInterface $mock): void {
            $mock->shouldReceive('batch')
                ->once()
                ->withArgs(function (array $requests): bool {
                    $this->assertSame([
                        'r' => 'json',
                        'plot' => 'full',
                        'i' => 'tt123',
                    ], $requests['one']['query']);

                    $this->assertSame([
                        'r' => 'json',
                        'plot' => 'full',
                        'i' => 'tt456',
                    ], $requests['two']['query']);

                    return true;
                })
                ->andReturn([
                    'one' => ['Response' => 'True'],
                    'two' => ['Response' => 'True'],
                ]);
        });

        $service = new OmdbClient($client, [
            'r' => 'json',
            'plot' => 'short',
        ]);

        $result = $service->batchFindByImdbIds([
            'one' => 'tt123',
            'two' => 'tt456',
        ], [
            'plot' => 'full',
        ]);

        $this->assertArrayHasKey('two', $result);
    }
}
