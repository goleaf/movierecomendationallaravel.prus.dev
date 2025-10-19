<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class QueueMetricsService
{
    public function __construct(
        private readonly RedisManager $redis,
    ) {}

    /**
     * @var array<string, string>
     */
    private const METRIC_KEY_MAP = [
        'queue' => 'jobs',
        'failed' => 'failed',
        'processed' => 'batches',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const CATEGORY_ALIAS_MAP = [
        'ingestion' => ['importers', 'ingestion', 'ingestion-high', 'ingestion-low'],
        'recommendations' => ['recommendations', 'recommender', 'recommendation', 'recommendation-high'],
    ];

    /**
     * @return array{
     *     jobs: int,
     *     failed: int,
     *     batches: int,
     *     horizon: array{workload: array<string, string>|null, supervisors: list<string>|null},
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
            $connection = $this->redis->connection();

            /** @var array<string, string>|false|int|string|null $workload */
            $workload = $connection->command('hgetall', ['horizon:workload']);
            if (is_array($workload) && $workload !== []) {
                $horizon['workload'] = array_map(static fn (mixed $value): string => (string) $value, $workload);
            }

            /** @var array<int, string>|false|int|string|null $supervisors */
            $supervisors = $connection->command('smembers', ['horizon:supervisors']);
            if (is_array($supervisors) && $supervisors !== []) {
                $horizon['supervisors'] = array_values(array_map(static fn (mixed $value): string => (string) $value, $supervisors));
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
     *     horizon: array{workload: array<string, string>|null, supervisors: list<string>|null},
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

    /**
     * @return array{
     *     pipelines: array<string, array{jobs: int, failed: int, queues: list<string>}>,
     *     uncategorized: array<string, array{jobs: int, failed: int}>,
     *     totals: array{jobs: int, failed: int},
     * }
     */
    public function queueBreakdown(): array
    {
        $jobsByQueue = $this->getQueueCounts('jobs');
        $failedByQueue = $this->getQueueCounts('failed_jobs');

        $pipelines = [];
        $categorizedQueues = [];

        foreach (self::CATEGORY_ALIAS_MAP as $pipeline => $aliases) {
            $jobs = 0;
            $failed = 0;

            foreach ($aliases as $alias) {
                $jobs += $jobsByQueue[$alias] ?? 0;
                $failed += $failedByQueue[$alias] ?? 0;

                if (array_key_exists($alias, $jobsByQueue) || array_key_exists($alias, $failedByQueue)) {
                    $categorizedQueues[] = $alias;
                }
            }

            $pipelines[$pipeline] = [
                'jobs' => $jobs,
                'failed' => $failed,
                'queues' => $aliases,
            ];
        }

        $uncategorized = [];
        $allQueues = array_unique(array_merge(array_keys($jobsByQueue), array_keys($failedByQueue)));

        foreach ($allQueues as $queueName) {
            if (in_array($queueName, $categorizedQueues, true)) {
                continue;
            }

            $uncategorized[$queueName] = [
                'jobs' => $jobsByQueue[$queueName] ?? 0,
                'failed' => $failedByQueue[$queueName] ?? 0,
            ];
        }

        $otherQueues = array_keys($uncategorized);
        $pipelines['other'] = [
            'jobs' => array_sum(array_map(static fn (array $queue): int => $queue['jobs'], $uncategorized)),
            'failed' => array_sum(array_map(static fn (array $queue): int => $queue['failed'], $uncategorized)),
            'queues' => $otherQueues,
        ];

        return [
            'pipelines' => $pipelines,
            'uncategorized' => $uncategorized,
            'totals' => [
                'jobs' => array_sum($jobsByQueue),
                'failed' => array_sum($failedByQueue),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getQueueCounts(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->select('queue', DB::raw('count(*) as aggregate'))
            ->groupBy('queue')
            ->pluck('aggregate', 'queue')
            ->map(static fn (mixed $value): int => (int) $value)
            ->toArray();
    }
}
