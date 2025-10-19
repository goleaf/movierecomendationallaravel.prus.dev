<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue as QueueFacade;
use RuntimeException;
use Tests\TestCase;

class QueueFailoverTest extends TestCase
{
    use RefreshDatabase;

    private bool $createdEnv = false;

    protected function setUp(): void
    {
        $envPath = dirname(__DIR__, 2).'/.env';

        if (! file_exists($envPath)) {
            file_put_contents($envPath, '');
            $this->createdEnv = true;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $envPath = dirname(__DIR__, 2).'/.env';

        if ($this->createdEnv && file_exists($envPath)) {
            unlink($envPath);
        }

        $this->createdEnv = false;
    }

    public function test_job_fails_over_to_database_when_redis_is_unavailable(): void
    {
        Event::fake([MessageLogged::class]);

        QueueFacade::extend('failing-redis', static function (): ConnectorInterface {
            return new class implements ConnectorInterface
            {
                public function connect(array $config): QueueContract
                {
                    return new class extends Queue implements QueueContract
                    {
                        public function size($queue = null): int
                        {
                            return 0;
                        }

                        public function push($job, $data = '', $queue = null): never
                        {
                            throw new RuntimeException('Redis connection failed.');
                        }

                        public function pushRaw($payload, $queue = null, array $options = []): never
                        {
                            throw new RuntimeException('Redis connection failed.');
                        }

                        public function later($delay, $job, $data = '', $queue = null): never
                        {
                            throw new RuntimeException('Redis connection failed.');
                        }

                        public function pop($queue = null): never
                        {
                            throw new RuntimeException('Redis connection failed.');
                        }
                    };
                }
            };
        });

        config([
            'queue.default' => 'failover',
            'queue.connections.failover.connections' => ['redis', 'database'],
            'queue.connections.redis.driver' => 'failing-redis',
        ]);

        QueueFailoverTestJob::dispatch('ingest-recommendation');

        $this->assertDatabaseCount('jobs', 1);

        $job = QueueFacade::connection('database')->pop();

        $this->assertNotNull($job);

        $job->fire();
        $job->delete();

        $this->assertDatabaseCount('jobs', 0);

        Event::assertDispatched(MessageLogged::class, static function (MessageLogged $log): bool {
            return $log->level === 'info'
                && $log->message === 'queue failover executed'
                && ($log->context['payload'] ?? null) === 'ingest-recommendation'
                && ($log->context['connection'] ?? null) === 'database';
        });
    }
}

class QueueFailoverTestJob implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Bus\Queueable;
    use \Illuminate\Foundation\Bus\Dispatchable;
    use \Illuminate\Queue\InteractsWithQueue;
    use \Illuminate\Queue\SerializesModels;

    public function __construct(private readonly string $payload)
    {
        $this->onConnection('failover');
    }

    public function handle(): void
    {
        $connection = $this->job?->getConnectionName();

        Log::info('queue failover executed', [
            'payload' => $this->payload,
            'connection' => $connection,
        ]);
    }
}
