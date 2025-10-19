<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class QueueHealthcheck extends Command
{
    protected $signature = 'queue:healthcheck';

    protected $description = 'Validate the health of the queue failover configuration.';

    public function handle(QueueManager $queue): int
    {
        $redisHealthy = $this->checkRedis();
        $fallbackHealthy = $this->checkFallbackConnections($queue);

        if ($redisHealthy && $fallbackHealthy) {
            $this->info('Queue connections are healthy.');

            return self::SUCCESS;
        }

        if (! $redisHealthy) {
            $this->error('Redis queue connection is unavailable.');
        }

        if (! $fallbackHealthy) {
            $this->error('One or more fallback queue connections are unavailable.');
        }

        return self::FAILURE;
    }

    private function checkRedis(): bool
    {
        try {
            $connectionName = config('queue.connections.redis.connection', 'default');

            Redis::connection($connectionName)->ping();

            $this->info(sprintf('Redis connection [%s] is healthy.', $connectionName));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Queue redis healthcheck failed.', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function checkFallbackConnections(QueueManager $queue): bool
    {
        $connections = (array) config('queue.connections.failover.connections', []);

        if ($connections === []) {
            return true;
        }

        $healthy = true;

        foreach ($connections as $connection) {
            if ($connection === 'redis') {
                continue;
            }

            try {
                $queueConnection = $queue->connection($connection);

                $queueConnection->size();

                $this->info(sprintf('Queue connection [%s] is healthy.', $connection));
            } catch (Throwable $exception) {
                Log::warning('Queue fallback healthcheck failed.', [
                    'connection' => $connection,
                    'exception' => $exception,
                ]);

                $healthy = false;
            }
        }

        return $healthy;
    }
}
