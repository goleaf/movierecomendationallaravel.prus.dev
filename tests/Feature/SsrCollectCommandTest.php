<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\StoreSsrMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SsrCollectCommandTest extends TestCase
{
    public function test_it_dispatches_metrics_for_configured_paths(): void
    {
        Queue::fake();

        config()->set('ssrmetrics.enabled', true);
        config()->set('ssrmetrics.paths', ['/', '/trends']);

        $paths = [];

        $this->artisan('ssr:collect')
            ->assertExitCode(Command::SUCCESS);

        Queue::assertPushed(StoreSsrMetric::class, static function (StoreSsrMetric $job) use (&$paths): bool {
            $paths[] = $job->payload['path'] ?? null;

            return true;
        });

        self::assertCount(2, $paths);
        self::assertSameCanonicalizing(['/', '/trends'], array_filter($paths));
    }

    public function test_it_skips_when_feature_disabled(): void
    {
        Queue::fake();

        config()->set('ssrmetrics.enabled', false);
        config()->set('ssrmetrics.paths', ['/', '/trends']);

        $this->artisan('ssr:collect')
            ->assertExitCode(Command::SUCCESS);

        Queue::assertNothingPushed();
    }
}
