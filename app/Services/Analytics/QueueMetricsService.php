<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Redis\Connections\Connection as RedisConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Predis\ClientInterface;

class QueueMetricsService
{
    /**
     * @var array<string, string>
     */
    private const METRIC_KEY_MAP = [
        'queue' => 'jobs',
        'failed' => 'failed',
        'processed' => 'batches',
    ];

    /**
     * @return array{
     *     jobs: int,
     *     failed: int,
     *     batches: int,
     *     horizon: array{workload: array<string, string>|null, supervisors: array<int, string>|null},
     * }
     */
    public function snapshot(): array
    {
        $jobs = Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0;
        $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
        $batches = Schema::hasTable('job_batches') ? (int) DB::table('job_batches')->count() : 0;

        $horizon = [
            'workload' => null,
            'supervisors' => null,
        ];

        try {
            /** @var RedisConnection $connection */
            $connection = Redis::connection();

            $client = $connection->client();

            $workloadRaw = [];
            $supervisorsRaw = [];

            if ($client instanceof \Redis) {
                $workloadRaw = $client->hGetAll('horizon:workload') ?: [];
                $supervisorsRaw = $client->sMembers('horizon:supervisors') ?: [];
            } elseif ($client instanceof ClientInterface) {
                $workloadRaw = $client->hgetall('horizon:workload');
                $supervisorsRaw = $client->smembers('horizon:supervisors');
            }

            $workload = [];
            if (is_array($workloadRaw)) {
                foreach ($workloadRaw as $field => $value) {
                    if (is_string($field)) {
                        $workload[$field] = (string) $value;
                    }
                }
            }

            $supervisors = [];
            if (is_array($supervisorsRaw)) {
                $supervisors = array_values(array_map(static fn (mixed $value): string => (string) $value, $supervisorsRaw));
            }

            if (! empty($workload)) {
                $horizon['workload'] = $workload;
            }

            if (! empty($supervisors)) {
                $horizon['supervisors'] = $supervisors;
            }
        } catch (\Throwable) {
            // Horizon might not be configured locally.
        }

        return [
            'jobs' => $jobs,
            'failed' => $failed,
            'batches' => $batches,
            'horizon' => $horizon,
        ];
    }

    /**
     * @return array{
     *     queue: int,
     *     failed: int,
     *     processed: int,
     *     horizon: array{workload: array<string, string>|null, supervisors: array<int, string>|null},
     * }
     */
    public function getMetrics(): array
    {
        $snapshot = $this->snapshot();

        /**
         * @var array{
         *     queue: int,
         *     failed: int,
         *     processed: int,
         *     horizon?: array{workload: array<string, string>|null, supervisors: array<int, string>|null}
         * } $metrics
         */
        $metrics = [];

        foreach (self::METRIC_KEY_MAP as $uiKey => $snapshotKey) {
            $metrics[$uiKey] = $snapshot[$snapshotKey];
        }

        $metrics['horizon'] = $snapshot['horizon'];

        return $metrics;
    }
}
