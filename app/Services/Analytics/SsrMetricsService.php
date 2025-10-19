<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SsrMetricsService
{
    public function hasMetrics(): bool
    {
        return Schema::hasTable('ssr_metrics');
    }

    public function latestSummary(): ?array
    {
        if (! $this->hasMetrics()) {
            return null;
        }

        $latestRecordedAt = DB::table('ssr_metrics')->max('recorded_at');

        if ($latestRecordedAt === null) {
            return null;
        }

        $latestDate = Carbon::parse($latestRecordedAt)->toDateString();

        $summary = DB::table('ssr_metrics')
            ->selectRaw('avg(score) as avg_score, count(distinct path) as path_count')
            ->whereDate('recorded_at', $latestDate)
            ->first();

        if ($summary === null) {
            return null;
        }

        return [
            'average_score' => (float) $summary->avg_score,
            'path_count' => (int) $summary->path_count,
            'recorded_date' => $latestDate,
        ];
    }

    public function scoreTrend(int $days = 30): Collection
    {
        if (! $this->hasMetrics()) {
            return collect();
        }

        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        return DB::table('ssr_metrics')
            ->selectRaw('date(recorded_at) as recorded_date, avg(score) as avg_score')
            ->where('recorded_at', '>=', $since)
            ->groupBy('recorded_date')
            ->orderBy('recorded_date')
            ->get()
            ->map(static fn ($row): array => [
                'recorded_date' => $row->recorded_date,
                'average_score' => round((float) $row->avg_score, 2),
            ]);
    }

    public function dropComparisonQuery(Carbon $firstDay, Carbon $secondDay): ?Builder
    {
        if (! $this->hasMetrics()) {
            return null;
        }

        $first = $firstDay->toDateString();
        $second = $secondDay->toDateString();

        return DB::query()
            ->fromSub(function ($query) use ($first, $second) {
                $query
                    ->fromSub(function ($aggregateQuery) use ($first, $second) {
                        $aggregateQuery
                            ->from('ssr_metrics')
                            ->selectRaw('path, date(recorded_at) as recorded_date, avg(score) as avg_score')
                            ->whereIn(DB::raw('date(recorded_at)'), [$first, $second])
                            ->groupBy('path', 'recorded_date');
                    }, 'daily_averages')
                    ->selectRaw(
                        'path,
                        max(case when recorded_date = ? then avg_score end) as score_yesterday,
                        max(case when recorded_date = ? then avg_score end) as score_today',
                        [$second, $first]
                    )
                    ->groupBy('path');
            }, 'pivot')
            ->select([
                DB::raw('row_number() over (order by coalesce(score_today, 0) - coalesce(score_yesterday, 0), path) as id'),
                'path',
                DB::raw('coalesce(score_today, 0) as score_today'),
                DB::raw('coalesce(score_yesterday, 0) as score_yesterday'),
                DB::raw('coalesce(score_today, 0) - coalesce(score_yesterday, 0) as delta'),
            ])
            ->whereRaw('(coalesce(score_today, 0) - coalesce(score_yesterday, 0)) < 0')
            ->orderBy('delta');
    }

    public function issuesSince(Carbon $since): Collection
    {
        if (! $this->hasMetrics()) {
            return collect();
        }

        return DB::table('ssr_metrics')
            ->selectRaw('path, avg(score) as avg_score, avg(blocking_scripts) as avg_blocking, avg(ldjson_count) as avg_ldjson, avg(og_count) as avg_og')
            ->where('recorded_at', '>=', $since)
            ->groupBy('path')
            ->get();
    }
}
