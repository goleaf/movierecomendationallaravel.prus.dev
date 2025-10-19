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
    public function test_tmdb_client_builds_signed_percent_encoded_uri(string $value): void
    {
        $tmdbRateLimitedClient = $this->createMock(RateLimitedClient::class);

        $tmdbRateLimitedClient->expects($this->once())
            ->method('get')
            ->with(
                $this->identicalTo('find/'.rawurlencode($value)),
                $this->identicalTo([
                    'external_source' => 'imdb_id',
                    'language' => 'en-US',
                    'api_key' => 'abc 123',
                ]),
            )
            ->willReturn([]);

        $tmdbClient = new TmdbClient($tmdbRateLimitedClient, 'en-US', [], 'abc 123');

        $tmdbClient->findByImdbId($value);
    }

    #[DataProvider('percentEncodedValuesProvider')]
    public function test_omdb_client_builds_signed_percent_encoded_query(string $value): void
    {
        $defaultParameters = ['plot' => 'short'];

        $omdbRateLimitedClient = $this->createMock(RateLimitedClient::class);

        $omdbRateLimitedClient->expects($this->once())
            ->method('get')
            ->with(
                $this->identicalTo('/'),
                $this->callback(function (array $query) use ($value, $defaultParameters): bool {
                    $this->assertSame(
                        array_merge($defaultParameters, [
                            's' => $value,
                            'apikey' => 'abc 123',
                        ]),
                        $query,
                    );

                    return true;
                }),
            )
            ->willReturn([]);

        $omdbClient = new OmdbClient($omdbRateLimitedClient, $defaultParameters, 'abc 123');

        $omdbClient->search($value);
    }

    public function test_omdb_client_ignores_empty_override_values(): void
    {
        $omdbRateLimitedClient = $this->createMock(RateLimitedClient::class);

        $omdbRateLimitedClient->expects($this->once())
            ->method('get')
            ->with(
                $this->identicalTo('/'),
                $this->identicalTo([
                    't' => 'Inception',
                    'apikey' => 'abc 123',
                ]),
            )
            ->willReturn([]);

        $omdbClient = new OmdbClient($omdbRateLimitedClient, [], 'abc 123');

        $omdbClient->findByTitle('Inception', ['plot' => '']);
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
