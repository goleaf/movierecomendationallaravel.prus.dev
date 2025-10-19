<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Http\Policy;
use Illuminate\Http\Client\PendingRequest;
use Tests\TestCase;

class HttpPolicyTest extends TestCase
{
    public function test_external_policy_applies_defaults(): void
    {
        $request = Policy::external();

        $applicationName = config('app.name');
        $expectedUserAgent = (is_string($applicationName) && $applicationName !== ''
            ? $applicationName
            : 'Laravel').'/HttpClient';

        $state = $this->inspectRequest($request);

        $this->assertSame(15.0, $state['options']['timeout']);
        $this->assertSame(5.0, $state['options']['connect_timeout']);
        $this->assertSame('application/json', $state['options']['headers']['Accept']);
        $this->assertSame($expectedUserAgent, $state['options']['headers']['User-Agent']);
        $this->assertSame(3, $state['tries']);
        $this->assertSame(200, $state['retryDelay']);
        $this->assertFalse($state['retryThrow']);
    }

    public function test_named_policy_overrides_defaults_and_builds_options(): void
    {
        $request = Policy::for('tmdb');
        $state = $this->inspectRequest($request);

        $this->assertSame(10.0, $state['options']['timeout']);
        $this->assertSame(5.0, $state['options']['connect_timeout']);
        $this->assertSame(2, $state['tries']);
        $this->assertSame(250, $state['retryDelay']);
        $this->assertFalse($state['retryThrow']);

        $options = Policy::options('tmdb');

        $this->assertSame(10.0, $options['options']['timeout']);
        $this->assertSame(5.0, $options['options']['connect_timeout']);
        $this->assertSame('application/json', $options['headers']['Accept']);
        $this->assertArrayHasKey('User-Agent', $options['headers']);
    }

    public function test_policy_can_disable_idempotent_retries(): void
    {
        config()->set('services.http.clients.non-idempotent', [
            'retry' => [
                'times' => 2,
                'sleep' => 125,
                'idempotent' => false,
            ],
        ]);

        $request = Policy::for('non-idempotent');
        $state = $this->inspectRequest($request);

        $this->assertSame(2, $state['tries']);
        $this->assertSame(125, $state['retryDelay']);
        $this->assertTrue($state['retryThrow']);
    }

    /**
     * @return array{options: array<string, mixed>, tries: int|null, retryDelay: int|null, retryThrow: bool|null}
     */
    private function inspectRequest(PendingRequest $request): array
    {
        return (function (): array {
            return [
                'options' => $this->options,
                'tries' => $this->tries,
                'retryDelay' => $this->retryDelay,
                'retryThrow' => $this->retryThrow,
            ];
        })->call($request);
    }
}
