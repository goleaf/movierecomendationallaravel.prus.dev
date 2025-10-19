<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MovieApis\OmdbClient;
use App\Services\MovieApis\RateLimitedClient;
use App\Services\MovieApis\TmdbClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientUriBuildTest extends TestCase
{
    #[DataProvider('percentEncodedValuesProvider')]
    public function test_clients_percent_encode_segments_and_query(string $value): void
    {
        $tmdbRateLimitedClient = $this->createMock(RateLimitedClient::class);

        $tmdbRateLimitedClient->expects($this->once())
            ->method('get')
            ->with(
                $this->identicalTo('find/'.rawurlencode($value)),
                $this->identicalTo([
                    'external_source' => 'imdb_id',
                    'language' => 'en-US',
                ]),
            )
            ->willReturn([]);

        $tmdbClient = new TmdbClient($tmdbRateLimitedClient, 'en-US');

        $tmdbClient->findByImdbId($value);

        $defaultParameters = ['apikey' => 'abc 123'];

        $omdbRateLimitedClient = $this->createMock(RateLimitedClient::class);

        $omdbRateLimitedClient->expects($this->once())
            ->method('get')
            ->with(
                $this->identicalTo('/'),
                $this->callback(function (array $query) use ($value, $defaultParameters): bool {
                    $expected = http_build_query(
                        array_merge($defaultParameters, ['s' => $value]),
                        '',
                        '&',
                        PHP_QUERY_RFC3986,
                    );

                    $this->assertSame(
                        $expected,
                        http_build_query($query, '', '&', PHP_QUERY_RFC3986),
                    );

                    return true;
                }),
            )
            ->willReturn([]);

        $omdbClient = new OmdbClient($omdbRateLimitedClient, $defaultParameters);

        $omdbClient->search($value);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function percentEncodedValuesProvider(): iterable
    {
        yield 'space' => ['tt 123 / sequel'];
        yield 'unicode' => ['tté123☂'];
        yield 'reserved' => ['tt/123?&='];
    }
}
