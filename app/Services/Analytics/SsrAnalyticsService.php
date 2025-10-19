<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\SsrMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SsrAnalyticsService
{
    /**
     * @return array{label: string, score: int, paths: int, description: string}
     */
    public function headline(): array
    {
        $score = 0;
        $paths = 0;

        if (Schema::hasTable('ssr_metrics')) {
            $row = DB::table('ssr_metrics')->orderByDesc('id')->first();

            if ($row) {
                $score = (int) $row->score;
                $paths = 1;
            }
        } elseif (Storage::exists('metrics/last.json')) {
            $json = json_decode(Storage::get('metrics/last.json'), true) ?: [];
            $paths = count($json);

            $total = 0;
            foreach ($json as $entry) {
                $total += (int) ($entry['score'] ?? 0);
            }

            if ($paths > 0) {
                $score = (int) round($total / $paths);
            }
        }

        return [
            'label' => __('analytics.widgets.ssr_stats.label'),
            'score' => $score,
            'paths' => $paths,
            'description' => trans_choice(
                'analytics.widgets.ssr_stats.description',
                $paths,
                ['count' => number_format($paths)]
            ),
        ];
    }

    /**
     * @return array{datasets: array<int, array{label: string, data: array<int, float>}>, labels: array<int, string>}
     */
    public function trend(int $limit = 30): array
    {
        $labels = [];
        $series = [];

        if (Schema::hasTable('ssr_metrics')) {
            $timestampColumn = $this->timestampColumn();

            $rows = DB::table('ssr_metrics')
                ->selectRaw("date({$timestampColumn}) as d, avg(score) as s")
                ->groupBy('d')
                ->orderBy('d')
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $labels[] = $row->d;
                $series[] = round((float) $row->s, 2);
            }
        } elseif (Storage::exists('metrics/last.json')) {
            $json = json_decode(Storage::get('metrics/last.json'), true) ?: [];

            $labels[] = now()->toDateString();
            $avg = 0;
            $count = 0;

            foreach ($json as $row) {
                $avg += (int) ($row['score'] ?? 0);
                $count++;
            }

            $series[] = $count > 0 ? round($avg / $count, 2) : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => __('analytics.widgets.ssr_score.dataset'),
                    'data' => $series,
                ],
            ],
            'labels' => $labels,
        ];
    }

    public function dropQuery(): Builder|Relation|null
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return null;
        }

        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();
        $timestampColumn = $this->timestampColumn();

        return SsrMetric::query()
            ->fromSub(function ($query) use ($today, $yesterday, $timestampColumn): void {
                $query
                    ->fromSub(function ($aggregateQuery) use ($today, $yesterday, $timestampColumn): void {
                        $aggregateQuery
                            ->from('ssr_metrics')
                            ->selectRaw("path, date({$timestampColumn}) as d, avg(score) as avg_score")
                            ->whereIn(DB::raw("date({$timestampColumn})"), [$yesterday, $today])
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
        return Schema::hasColumn('ssr_metrics', 'recorded_at') ? 'recorded_at' : 'created_at';
    }
}
