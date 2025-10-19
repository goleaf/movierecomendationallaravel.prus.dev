<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectSsrMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_collects_metrics_for_configured_paths(): void
    {
        config()->set('ssrmetrics.enabled', true);
        config()->set('ssrmetrics.paths', ['/']);
        config()->set('queue.default', 'sync');

        $this->artisan('ssr:collect')->assertSuccessful();

        $this->assertDatabaseHas('ssr_metrics', [
            'path' => '/',
        ]);
    }
}
