<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Str;
use Throwable;

class QueueHealthcheckCommand extends Command
{
    protected $signature = 'queue:healthcheck';

    protected $description = 'Verify Redis and database queue connectivity.';

    public function handle(RedisManager $redis, ConnectionResolverInterface $connections): int
    {
        $failoverOrder = $this->formatFailoverOrder();

        if ($failoverOrder !== '') {
            $this->line(sprintf('Failover order: %s', $failoverOrder));
        }

        $healthy = true;

        if (! $this->checkRedis($redis)) {
            $healthy = false;
        }

        if (! $this->checkDatabase($connections)) {
            $healthy = false;
        }

        if (! $healthy) {
            return static::FAILURE;
        }

        $this->info('Queue healthcheck passed.');

        return static::SUCCESS;
    }

    private function formatFailoverOrder(): string
    {
        $connections = config('queue.connections.failover.connections', []);

        if (! is_array($connections) || $connections === []) {
            return '';
        }

        $cleanedConnections = array_values(array_filter(
            array_map(
                static fn ($connection): string => (string) Str::of($connection)->trim(),
                $connections,
            ),
            static fn (string $connection): bool => $connection !== '',
        ));

        return implode(' -> ', $cleanedConnections);
    }

    private function checkRedis(RedisManager $redis): bool
    {
        $connectionName = (string) config('queue.connections.redis.connection', 'default');

        $this->line(sprintf('Checking Redis queue connection (%s)...', $connectionName));

        try {
            $redis->connection($connectionName)->ping();
        } catch (Throwable $exception) {
            $this->error(sprintf('Redis queue connection failed: %s', $exception->getMessage()));
            report($exception);

            return false;
        }

        $this->info('Redis queue connection is healthy.');

        return true;
    }

    private function checkDatabase(ConnectionResolverInterface $connections): bool
    {
        $connectionName = config('queue.connections.database.connection');
        $table = (string) config('queue.connections.database.table', 'jobs');
        $displayName = $connectionName ?: config('database.default');

        $this->line(sprintf('Checking database queue connection (%s:%s)...', $displayName, $table));

        try {
            $connections->connection($connectionName)->table($table)->limit(1)->get();
        } catch (Throwable $exception) {
            $this->error(sprintf('Database queue connection failed: %s', $exception->getMessage()));
            report($exception);

            return false;
        }

        $this->info('Database queue connection is healthy.');

        return true;
    }
}
