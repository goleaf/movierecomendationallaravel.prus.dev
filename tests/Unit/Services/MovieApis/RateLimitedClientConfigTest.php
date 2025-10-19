<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MovieApis;

use App\Services\MovieApis\RateLimitedClientConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RateLimitedClientConfigTest extends TestCase
{
    public function test_it_normalises_configuration(): void
    {
        $config = new RateLimitedClientConfig(
            baseUrl: ' https://example.com ',
            timeout: 5.0,
            retry: [
                'attempts' => '2',
                'delay_ms' => 250,
            ],
            backoff: [
                'multiplier' => 2,
                'max_delay_ms' => 2_000,
            ],
            rateLimit: [
                'window' => 120,
                'allowance' => 10,
            ],
            defaultQuery: [
                'api_key' => 'secret',
                'locale' => null,
            ],
            defaultHeaders: [
                'X-Test' => 'value',
            ],
            rateLimiterKey: ' custom-key ',
        );

        $this->assertSame('https://example.com/', $config->baseUrl());
        $this->assertSame(5.0, $config->timeout());
        $this->assertSame(2, $config->retryAttempts());
        $this->assertSame(250, $config->retryDelayMs());
        $this->assertSame(2.0, $config->backoffMultiplier());
        $this->assertSame(2_000, $config->backoffMaxDelayMs());
        $this->assertSame(120, $config->rateLimitWindow());
        $this->assertSame(10, $config->rateLimitAllowance());
        $this->assertSame([
            'api_key' => 'secret',
        ], $config->defaultQuery());
        $this->assertSame([
            'X-Test' => 'value',
        ], $config->defaultHeaders());
        $this->assertSame('custom-key', $config->rateLimiterKey());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function invalidConfigurationProvider(): array
    {
        return [
            'empty base url' => [
                'args' => [
                    'baseUrl' => '   ',
                    'timeout' => 1.0,
                ],
            ],
            'zero timeout' => [
                'args' => [
                    'baseUrl' => 'https://example.com',
                    'timeout' => 0.0,
                ],
            ],
            'negative retry attempts' => [
                'args' => [
                    'baseUrl' => 'https://example.com',
                    'timeout' => 1.0,
                    'retry' => [
                        'attempts' => -1,
                    ],
                ],
            ],
            'non string header key' => [
                'args' => [
                    'baseUrl' => 'https://example.com',
                    'timeout' => 1.0,
                    'defaultHeaders' => [
                        0 => 'value',
                    ],
                ],
            ],
            'empty rate limiter key' => [
                'args' => [
                    'baseUrl' => 'https://example.com',
                    'timeout' => 1.0,
                    'rateLimiterKey' => '   ',
                ],
            ],
        ];
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function test_it_rejects_invalid_configuration(array $args): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RateLimitedClientConfig(
            baseUrl: $args['baseUrl'],
            timeout: $args['timeout'],
            retry: $args['retry'] ?? [],
            backoff: $args['backoff'] ?? [],
            rateLimit: $args['rateLimit'] ?? [],
            defaultQuery: $args['defaultQuery'] ?? [],
            defaultHeaders: $args['defaultHeaders'] ?? [],
            rateLimiterKey: $args['rateLimiterKey'] ?? null,
        );
    }
}
