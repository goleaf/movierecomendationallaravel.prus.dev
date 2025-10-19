<?php

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
        $jobs = Schema::hasTable('jobs') ? (int) (DB::table('jobs')->count() ?? 0) : 0;
        $failed = Schema::hasTable('failed_jobs') ? (int) (DB::table('failed_jobs')->count() ?? 0) : 0;
        $batches = Schema::hasTable('job_batches') ? (int) (DB::table('job_batches')->count() ?? 0) : 0;

        $horizon = [
            'workload' => null,
            'supervisors' => null,
        ];

        try {
            $redis = Redis::connection();

            /** @var array<string, string>|null $workload */
            $workload = null;
            /** @var array<int, string>|null $supervisors */
            $supervisors = null;

            if ($redis instanceof PhpRedisConnection) {
                /** @var \Redis $client */
                $client = $redis->client();
                $workloadData = $client->hGetAll('horizon:workload');
                if (is_array($workloadData) && $workloadData !== []) {
                    $workload = array_map(static fn ($value): string => (string) $value, $workloadData);
                }
                $supervisorData = $client->sMembers('horizon:supervisors');
                if (is_array($supervisorData) && $supervisorData !== []) {
                    $supervisors = array_map(static fn ($value): string => (string) $value, $supervisorData);
                }
            } elseif ($redis instanceof PredisConnection) {
                /** @var \Predis\ClientInterface $client */
                $client = $redis->client();
                $workloadData = $client->hgetall('horizon:workload');
                if ($workloadData !== []) {
                    $workload = array_map(static fn ($value): string => (string) $value, $workloadData);
                }
                $supervisorData = $client->smembers('horizon:supervisors');
                if ($supervisorData !== []) {
                    $supervisors = array_map(static fn ($value): string => (string) $value, $supervisorData);
                }
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

        return [
            'queue' => (int) $snapshot['jobs'],
            'failed' => (int) $snapshot['failed'],
            'processed' => (int) $snapshot['batches'],
            'horizon' => $snapshot['horizon'],
        ];
    }
}
