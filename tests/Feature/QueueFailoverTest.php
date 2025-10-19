<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue as QueueFacade;
use RuntimeException;
use Tests\TestCase;

class QueueFailoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_fall_back_to_database_when_redis_is_unavailable(): void
    {
        QueueFacade::extend('failing-redis', function ($app): QueueContract {
            return new class extends Queue implements QueueContract
            {
                public function size($queue = null): int
                {
                    return 0;
                }

                public function pushRaw($payload, $queue = null, array $options = []): string
                {
                    throw new RuntimeException('Redis is unavailable.');
                }

                public function later($delay, $job, $data = '', $queue = null)
                {
                    throw new RuntimeException('Redis is unavailable.');
                }

                public function pop($queue = null)
                {
                    throw new RuntimeException('Redis is unavailable.');
                }
            };
        });

        config([
            'queue.default' => 'failover',
            'queue.connections.redis.driver' => 'failing-redis',
        ]);

        Cache::store('array')->forget('queue-failover::handled');

        dispatch((new FailoverTestJob('queue-failover::handled'))->onConnection('failover'));

        $databaseQueue = QueueFacade::connection('database');
        $job = $databaseQueue->pop();

        $this->assertNotNull($job, 'Job should have been stored on the database queue.');

        $job->fire();

        $this->assertTrue(Cache::store('array')->get('queue-failover::handled', false));
    }
}

final class FailoverTestJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public function __construct(private readonly string $cacheKey) {}

    public function handle(): void
    {
        Cache::store('array')->put($this->cacheKey, true);
    }
}
