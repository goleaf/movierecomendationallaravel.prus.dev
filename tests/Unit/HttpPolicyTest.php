<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Http\Policy;
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

        $state = (function (): array {
            return [
                'options' => $this->options,
                'tries' => $this->tries,
                'retryDelay' => $this->retryDelay,
                'retryThrow' => $this->retryThrow,
            ];
        })->call($request);

        $this->assertSame(15, $state['options']['timeout']);
        $this->assertSame(5, $state['options']['connect_timeout']);
        $this->assertSame('application/json', $state['options']['headers']['Accept']);
        $this->assertSame($expectedUserAgent, $state['options']['headers']['User-Agent']);
        $this->assertSame(3, $state['tries']);
        $this->assertSame(200, $state['retryDelay']);
        $this->assertFalse($state['retryThrow']);
    }

    public function test_external_policy_allows_header_overrides(): void
    {
        $request = Policy::external()->replaceHeaders([
            'Accept' => '*/*',
            'User-Agent' => 'Custom/Client',
        ]);

        $options = $request->getOptions();

        $this->assertSame('*/*', $options['headers']['Accept']);
        $this->assertSame('Custom/Client', $options['headers']['User-Agent']);
        $this->assertSame(15, $options['timeout']);
        $this->assertSame(5, $options['connect_timeout']);
    }
}
