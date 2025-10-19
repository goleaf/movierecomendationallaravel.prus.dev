<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MovieApis;

use App\Services\MovieApis\RateLimitedClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class RateLimitedClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Http::fake();
        parent::tearDown();
    }

    public function test_hooks_trigger_for_successful_request(): void
    {
        RateLimiter::clear('hooks:success');

        Http::fake([
            'api.test/*' => Http::response(['ok' => true], 200),
        ]);

        $beforeContext = null;
        $successContext = null;

        $client = new RateLimitedClient(
            Http::getFacadeRoot(),
            'https://api.test/',
            5.0,
            [],
            [],
            ['window' => 10, 'allowance' => 5],
            [],
            [],
            'hooks:success',
            [
                'before_request' => function (array $context) use (&$beforeContext): void {
                    $beforeContext = $context;
                },
                'success' => function (array $context) use (&$successContext): void {
                    $successContext = $context;
                },
            ],
        );

        $result = $client->get('resource');

        $this->assertSame(['ok' => true], $result);
        $this->assertNotNull($beforeContext);
        $this->assertSame('GET', $beforeContext['method']);
        $this->assertNotNull($successContext);
        $this->assertSame(1, $successContext['attempt']);
        Http::assertSentCount(1);
    }

    public function test_retry_hook_triggers_for_retryable_response(): void
    {
        RateLimiter::clear('hooks:retry');

        Http::fakeSequence()
            ->push([], 500)
            ->push(['ok' => true], 200);

        $retryContexts = [];
        $successContext = null;

        $client = new RateLimitedClient(
            Http::getFacadeRoot(),
            'https://api.test/',
            5.0,
            ['attempts' => 1, 'delay_ms' => 0],
            [],
            ['window' => 10, 'allowance' => 5],
            [],
            [],
            'hooks:retry',
            [
                'retry' => function (array $context) use (&$retryContexts): void {
                    $retryContexts[] = $context;
                },
                'success' => function (array $context) use (&$successContext): void {
                    $successContext = $context;
                },
            ],
        );

        $result = $client->get('resource');

        $this->assertSame(['ok' => true], $result);
        $this->assertCount(1, $retryContexts);
        $this->assertSame(1, $retryContexts[0]['attempt']);
        $this->assertSame(500, $retryContexts[0]['status']);
        $this->assertSame(2, $successContext['attempt']);
        Http::assertSentCount(2);
    }

    public function test_throttled_hook_triggers_when_rate_limit_exceeded(): void
    {
        RateLimiter::clear('hooks:throttle');

        Http::fake([
            'api.test/*' => Http::response(['ok' => true], 200),
        ]);

        $throttledContext = null;

        $client = new RateLimitedClient(
            Http::getFacadeRoot(),
            'https://api.test/',
            5.0,
            [],
            [],
            ['window' => 1, 'allowance' => 1],
            [],
            [],
            'hooks:throttle',
            [
                'throttled' => function (array $context) use (&$throttledContext): void {
                    $throttledContext = $context;
                },
            ],
        );

        $client->get('resource');

        try {
            $client->get('resource');
            $this->fail('Expected too many requests exception.');
        } catch (TooManyRequestsHttpException) {
            // Expected exception.
        }

        $this->assertNotNull($throttledContext);
        $this->assertSame('GET', $throttledContext['method']);
        $this->assertSame('hooks:throttle', $throttledContext['rate_limiter_key']);
    }
}
