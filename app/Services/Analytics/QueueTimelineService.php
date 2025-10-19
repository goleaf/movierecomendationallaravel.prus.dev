<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueTimelineService
{
    private const DEFAULT_MINUTES = 60;

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     interval_minutes: int,
     *     points: list<array{timestamp: string, jobs: int, failures: int}>
     * }
     */
    public function timeline(int $minutes = self::DEFAULT_MINUTES): array
    {
        $minutes = max(1, $minutes);

        $end = CarbonImmutable::now()->setSecond(0)->setMicrosecond(0);
        $start = $end->subMinutes($minutes - 1);

        $points = $this->initializePoints($start, $end);

        if (Schema::hasTable('jobs')) {
            $this->applyJobCounts($points, $start, $end);
        }

        if (Schema::hasTable('failed_jobs')) {
            $this->applyFailureCounts($points, $start, $end);
        }

        return [
            'from' => $start->toIso8601String(),
            'to' => $end->toIso8601String(),
            'interval_minutes' => $minutes,
            'points' => array_values($points),
        ];
    }

    /**
     * @param  array<string, array{timestamp: string, jobs: int, failures: int}>  $points
     */
    private function applyJobCounts(array &$points, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $rows = DB::table('jobs')
            ->select('created_at')
            ->whereBetween('created_at', [$start->getTimestamp(), $end->getTimestamp()])
            ->orderBy('created_at')
            ->get();

        foreach ($rows as $row) {
            $createdAt = $row->created_at ?? null;

            if ($createdAt === null) {
                continue;
            }

            $minute = CarbonImmutable::createFromTimestamp((int) $createdAt)
                ->setSecond(0)
                ->setMicrosecond(0);

            $key = $this->pointKey($minute);

            if (! isset($points[$key])) {
                continue;
            }

            $points[$key]['jobs']++;
        }
    }

    /**
     * @param  array<string, array{timestamp: string, jobs: int, failures: int}>  $points
     */
    private function applyFailureCounts(array &$points, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $rows = DB::table('failed_jobs')
            ->select('failed_at')
            ->whereBetween('failed_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('failed_at')
            ->get();

        foreach ($rows as $row) {
            $failedAt = $row->failed_at ?? null;

            if ($failedAt === null) {
                continue;
            }

            $minute = CarbonImmutable::parse((string) $failedAt)
                ->setSecond(0)
                ->setMicrosecond(0);

            $key = $this->pointKey($minute);

            if (! isset($points[$key])) {
                continue;
            }

            $points[$key]['failures']++;
        }
    }

    /**
     * @return array<string, array{timestamp: string, jobs: int, failures: int}>
     */
    private function initializePoints(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $points = [];
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $this->pointKey($cursor);

            $points[$key] = [
                'timestamp' => $cursor->toIso8601String(),
                'jobs' => 0,
                'failures' => 0,
            ];

            $cursor = $cursor->addMinute();
        }

        return $points;
    }

    private function pointKey(CarbonImmutable $moment): string
    {
        return $moment->format('Y-m-d H:i');
    }
}
