<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\SsrMetric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SsrAnalyticsService
{
    public function __construct(private readonly SsrMetricsAggregator $aggregator) {}

    /**
     * @return array{
     *     label: string,
     *     periods: array{
     *         today: array{score: float, first_byte_ms: float, samples: int, paths: int, delta: array{score: float, first_byte_ms: float, samples: int, paths: int}},
     *         yesterday: array{score: float, first_byte_ms: float, samples: int, paths: int},
     *         seven_days: array{score: float, first_byte_ms: float, samples: int, paths: int, range: array{from: string, to: string}, delta: array{score: float, first_byte_ms: float, samples: int, paths: int}},
     *     },
     * }
     */
    public function headline(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $yesterday = $today->subDay();
        $sevenStart = $today->subDays(6);
        $previousSevenStart = $sevenStart->subDays(7);
        $previousSevenEnd = $sevenStart->subDay();

        $todaySummary = $this->aggregator->aggregate($today, $today->endOfDay())['summary'];
        $yesterdaySummary = $this->aggregator->aggregate($yesterday, $yesterday->endOfDay())['summary'];
        $sevenSummary = $this->aggregator->aggregate($sevenStart, $today->endOfDay())['summary'];
        $previousSevenSummary = $this->aggregator->aggregate($previousSevenStart, $previousSevenEnd->endOfDay())['summary'];

        $todayDelta = $this->buildDelta($todaySummary, $yesterdaySummary);
        $sevenDayDelta = $this->buildDelta($sevenSummary, $previousSevenSummary);

        return [
            'label' => __('analytics.widgets.ssr_stats.label'),
            'periods' => [
                'today' => array_merge($todaySummary, [
                    'delta' => $todayDelta,
                ]),
                'yesterday' => $yesterdaySummary,
                'seven_days' => array_merge($sevenSummary, [
                    'range' => [
                        'from' => $sevenStart->toDateString(),
                        'to' => $today->toDateString(),
                    ],
                    'delta' => $sevenDayDelta,
                ]),
            ],
        ];
    }

    /**
     * @return array{datasets: array<int, array{label: string, data: array<int, float>}>, labels: array<int, string>}
     */
    public function trend(int $limit = 30): array
    {
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($limit - 1)->startOfDay();

        $aggregate = $this->aggregator->aggregate($start, $end);
        $daily = $aggregate['daily'];

        if ($aggregate['summary']['samples'] === 0) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $labels = array_column($daily, 'date');
        $dailyScores = array_map(static fn (array $day): float => (float) $day['score'], $daily);
        $rollingScores = array_map(static fn (array $day): float => (float) $day['rolling_score'], $daily);

        return [
            'datasets' => [
                [
                    'label' => __('analytics.widgets.ssr_score.datasets.daily'),
                    'data' => $dailyScores,
                ],
                [
                    'label' => __('analytics.widgets.ssr_score.datasets.rolling'),
                    'data' => $rollingScores,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @param  array{score: float, first_byte_ms: float, samples: int, paths: int}  $current
     * @param  array{score: float, first_byte_ms: float, samples: int, paths: int}  $previous
     * @return array{score: float, first_byte_ms: float, samples: int, paths: int}
     */
    private function buildDelta(array $current, array $previous): array
    {
        return [
            'score' => round($current['score'] - $previous['score'], 2),
            'first_byte_ms' => round($current['first_byte_ms'] - $previous['first_byte_ms'], 2),
            'samples' => $current['samples'] - $previous['samples'],
            'paths' => $current['paths'] - $previous['paths'],
        ];
    }

    public function dropQuery(): Builder|Relation|null
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return null;
        }

        $timestampColumn = $this->timestampColumn();
        $dateExpression = 'date('.$timestampColumn.')';
        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();

        return SsrMetric::query()
            ->fromSub(function ($query) use ($today, $yesterday, $timestampColumn, $dateExpression): void {
                $query
                    ->fromSub(function ($aggregateQuery) use ($today, $yesterday, $timestampColumn, $dateExpression): void {
                        $aggregateQuery
                            ->from('ssr_metrics')
                            ->selectRaw('path, '.$dateExpression.' as d, avg(score) as avg_score')
                            ->whereNotNull($timestampColumn)
                            ->whereIn(DB::raw($dateExpression), [$yesterday, $today])
                            ->groupBy('path', 'd');
                    }, 'agg')
                    ->selectRaw(
                        'path,
                        max(case when d = ? then avg_score end) as score_today,
                        max(case when d = ? then avg_score end) as score_yesterday',
                        [$today, $yesterday]
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

    /**
     * @return array<int, array{path: string, score_today: float, score_yesterday: float, delta: float}>
     */
    public function dropRows(int $limit = 10): array
    {
        $query = $this->dropQuery();

        if ($query === null) {
            return [];
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(static function ($row): array {
                return [
                    'path' => $row->path,
                    'score_today' => (float) $row->score_today,
                    'score_yesterday' => (float) $row->score_yesterday,
                    'delta' => (float) $row->delta,
                ];
            })
            ->all();
    }

    private function timestampColumn(): string
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return 'created_at';
        }

        return Schema::hasColumn('ssr_metrics', 'collected_at') ? 'collected_at' : 'created_at';
    }
}
