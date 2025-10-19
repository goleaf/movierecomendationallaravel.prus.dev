<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

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
            $connection = Redis::connection();

            $workload = $connection->command('hgetall', ['horizon:workload']);
            if (is_array($workload) && $workload !== []) {
                $horizon['workload'] = array_map(static fn ($value): string => (string) $value, $workload);
            }

            $supervisors = $connection->command('smembers', ['horizon:supervisors']);
            if (is_array($supervisors) && $supervisors !== []) {
                $horizon['supervisors'] = array_values(array_map(static fn ($value): string => (string) $value, $supervisors));
            }
        } catch (Throwable) {
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

        $metrics = [
            'queue' => 0,
            'failed' => 0,
            'processed' => 0,
        ];

        foreach (self::METRIC_KEY_MAP as $uiKey => $snapshotKey) {
            $metrics[$uiKey] = (int) $snapshot[$snapshotKey];
        }

        $metrics['horizon'] = $snapshot['horizon'];

        return $metrics;
    }
}
