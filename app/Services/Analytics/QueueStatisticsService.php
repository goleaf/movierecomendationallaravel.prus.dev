<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueStatisticsService
{
    /**
     * @return array{
     *     generated_at: CarbonImmutable,
     *     queues: array<int, array{
     *         queue: string,
     *         in_flight: int,
     *         failed: int,
     *         average_runtime_seconds: float,
     *         jobs_per_minute: float,
     *         processed_jobs: int,
     *         batches: int
     *     }>,
     *     totals: array{
     *         in_flight: int,
     *         failed: int,
     *         processed_jobs: int,
     *         batches: int,
     *         average_runtime_seconds: float,
     *         jobs_per_minute: float
     *     }
     * }
     */
    public function metrics(): array
    {
        $queues = [];

        $this->appendJobsInFlight($queues);
        $this->appendFailedJobs($queues);
        $this->appendBatchThroughput($queues);

        ksort($queues);

        $totals = [
            'in_flight' => 0,
            'failed' => 0,
            'processed_jobs' => 0,
            'batches' => 0,
            'average_runtime_seconds' => 0.0,
            'jobs_per_minute' => 0.0,
        ];

        $totalRuntimeSeconds = 0.0;
        $totalRuntimeBatches = 0;
        $totalDurationMinutes = 0.0;

        foreach ($queues as $queue => $stats) {
            $queues[$queue]['average_runtime_seconds'] = $stats['batches'] > 0
                ? round($stats['runtime_seconds'] / $stats['batches'], 2)
                : 0.0;

            $queues[$queue]['jobs_per_minute'] = $stats['duration_minutes'] > 0
                ? round($stats['processed_jobs'] / $stats['duration_minutes'], 2)
                : 0.0;

            unset($queues[$queue]['runtime_seconds'], $queues[$queue]['duration_minutes']);

            $totals['in_flight'] += $queues[$queue]['in_flight'];
            $totals['failed'] += $queues[$queue]['failed'];
            $totals['processed_jobs'] += $queues[$queue]['processed_jobs'];
            $totals['batches'] += $queues[$queue]['batches'];

            $totalRuntimeSeconds += $stats['runtime_seconds'];
            $totalRuntimeBatches += $stats['batches'];
            $totalDurationMinutes += $stats['duration_minutes'];
        }

        $totals['average_runtime_seconds'] = $totalRuntimeBatches > 0
            ? round($totalRuntimeSeconds / $totalRuntimeBatches, 2)
            : 0.0;

        $totals['jobs_per_minute'] = $totalDurationMinutes > 0
            ? round($totals['processed_jobs'] / $totalDurationMinutes, 2)
            : 0.0;

        return [
            'generated_at' => CarbonImmutable::now(),
            'queues' => array_values($queues),
            'totals' => $totals,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $queues
     */
    private function appendJobsInFlight(array &$queues): void
    {
        if (! Schema::hasTable('jobs')) {
            return;
        }

        $rows = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as aggregate'))
            ->groupBy('queue')
            ->get();

        foreach ($rows as $row) {
            $queue = $this->normalizeQueueName($row->queue ?? 'default');

            $queues[$queue] = $queues[$queue] ?? $this->blankStats($queue);
            $queues[$queue]['in_flight'] = (int) ($row->aggregate ?? 0);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $queues
     */
    private function appendFailedJobs(array &$queues): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            return;
        }

        $rows = DB::table('failed_jobs')
            ->select('queue', DB::raw('count(*) as aggregate'))
            ->groupBy('queue')
            ->get();

        foreach ($rows as $row) {
            $queue = $this->normalizeQueueName($row->queue ?? 'default');

            $queues[$queue] = $queues[$queue] ?? $this->blankStats($queue);
            $queues[$queue]['failed'] = (int) ($row->aggregate ?? 0);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $queues
     */
    private function appendBatchThroughput(array &$queues): void
    {
        if (! Schema::hasTable('job_batches')) {
            return;
        }

        $rows = DB::table('job_batches')
            ->select('options', 'created_at', 'finished_at', 'total_jobs', 'pending_jobs', 'failed_jobs', 'cancelled_jobs')
            ->whereNotNull('created_at')
            ->get();

        foreach ($rows as $row) {
            $queue = $this->queueFromOptions($row->options ?? null);

            if ($queue === null) {
                $queue = 'default';
            }

            $createdAt = $this->parseTimestamp($row->created_at ?? null);
            $finishedAt = $this->parseTimestamp($row->finished_at ?? null);

            if ($createdAt === null || $finishedAt === null) {
                continue;
            }

            $runtimeSeconds = max(1, $createdAt->diffInSeconds($finishedAt));
            $pending = (int) ($row->pending_jobs ?? 0);
            $failed = (int) ($row->failed_jobs ?? 0);
            $cancelled = (int) (property_exists($row, 'cancelled_jobs') ? ($row->cancelled_jobs ?? 0) : 0);
            $totalJobs = (int) ($row->total_jobs ?? 0);

            $processed = max(0, $totalJobs - $pending - $failed - $cancelled);

            $queues[$queue] = $queues[$queue] ?? $this->blankStats($queue);

            $queues[$queue]['processed_jobs'] += $processed;
            $queues[$queue]['batches']++;
            $queues[$queue]['runtime_seconds'] += $runtimeSeconds;
            $queues[$queue]['duration_minutes'] += $runtimeSeconds / 60;
        }
    }

    private function normalizeQueueName(string $queue): string
    {
        $queue = trim($queue);

        if ($queue === '') {
            return 'default';
        }

        return mb_strtolower($queue);
    }

    private function queueFromOptions(mixed $options): ?string
    {
        if ($options === null) {
            return null;
        }

        if (is_string($options)) {
            $decoded = json_decode($options, true);
        } elseif (is_array($options)) {
            $decoded = $options;
        } else {
            $decoded = null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $queue = Arr::get($decoded, 'queue');

        if ($queue === null) {
            return null;
        }

        return $this->normalizeQueueName((string) $queue);
    }

    private function parseTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestamp((int) $value);
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        return CarbonImmutable::parse($string);
    }

    /**
     * @return array{queue: string, in_flight: int, failed: int, processed_jobs: int, batches: int, runtime_seconds: float, duration_minutes: float, average_runtime_seconds: float, jobs_per_minute: float}
     */
    private function blankStats(string $queue): array
    {
        return [
            'queue' => $queue,
            'in_flight' => 0,
            'failed' => 0,
            'processed_jobs' => 0,
            'batches' => 0,
            'runtime_seconds' => 0.0,
            'duration_minutes' => 0.0,
            'average_runtime_seconds' => 0.0,
            'jobs_per_minute' => 0.0,
        ];
    }
}
