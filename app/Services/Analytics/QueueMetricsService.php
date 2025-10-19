<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class QueueMetricsService
{
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

        /** @var array<string, string>|null $workload */
        $workload = null;
        /** @var array<int, string>|null $supervisors */
        $supervisors = null;

        try {
            $connection = Redis::connection();

            if ($connection instanceof PhpRedisConnection || $connection instanceof PredisConnection) {
                $workloadData = $connection->hgetall('horizon:workload');
                if (is_array($workloadData) && $workloadData !== []) {
                    /** @var array<string, string> $workloadData */
                    $workload = array_map(static fn ($value): string => (string) $value, $workloadData);
                }

                $supervisorData = $connection->smembers('horizon:supervisors');
                if (is_array($supervisorData) && $supervisorData !== []) {
                    /** @var array<int, string> $normalizedSupervisors */
                    $normalizedSupervisors = array_map(static fn ($value): string => (string) $value, $supervisorData);
                    $supervisors = array_values($normalizedSupervisors);
                }
            }

        } catch (\Throwable) {
            // Horizon might not be configured locally.
        }

        if ($workload !== null) {
            $horizon['workload'] = $workload;
        }

        if ($supervisors !== null) {
            $horizon['supervisors'] = $supervisors;
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

        return [
            'queue' => $snapshot['jobs'],
            'failed' => $snapshot['failed'],
            'processed' => $snapshot['batches'],
            'horizon' => $snapshot['horizon'],
        ];
    }
}
