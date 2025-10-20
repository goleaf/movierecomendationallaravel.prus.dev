<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MovieApis\OmdbClient;
use App\Services\MovieApis\RateLimitedClient;
use App\Services\MovieApis\TmdbClient;
use App\Support\Http\MovieApiUriBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientUriBuildTest extends TestCase
{
    #[DataProvider('percentEncodedValuesProvider')]
    public function test_clients_percent_encode_segments_and_query(string $value): void
    {
        $tmdbRateLimitedClient = $this->createMock(RateLimitedClient::class);
        $tmdbApiKey = 'tmdb key '.$value;

        $tmdbRateLimitedClient->expects($this->once())
            ->method('get')
            ->with(
                $this->identicalTo('find/'.rawurlencode($value)),
                $this->identicalTo([
                    'api_key' => $tmdbApiKey,
                    'external_source' => 'imdb_id',
                    'language' => 'en-US',
                ]),
            )
            ->willReturn([]);

        $tmdbClient = new TmdbClient(
            $tmdbRateLimitedClient,
            'en-US',
            [],
            new MovieApiUriBuilder('api_key', $tmdbApiKey),
        );

        $tmdbClient->findByImdbId($value);

        $omdbApiKey = 'abc 123';
        $defaultParameters = ['r' => 'json'];

        $omdbRateLimitedClient = $this->createMock(RateLimitedClient::class);

        $omdbRateLimitedClient->expects($this->once())
            ->method('get')
            ->with(
                $this->identicalTo('/'),
                $this->callback(function (array $query) use ($value, $defaultParameters, $omdbApiKey): bool {
                    $expected = http_build_query(
                        array_merge([
                            'apikey' => $omdbApiKey,
                        ], $defaultParameters, ['s' => $value]),
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

        $omdbClient = new OmdbClient(
            $omdbRateLimitedClient,
            $defaultParameters,
            new MovieApiUriBuilder('apikey', $omdbApiKey),
        );

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
