<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\SsrMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
            $timestampColumn = $this->timestampColumn();

            $row = DB::table('ssr_metrics')
                ->whereNotNull($timestampColumn)
                ->orderByDesc($timestampColumn)
                ->orderByDesc('id')
                ->first();

            if ($row) {
                $score = (int) $row->score;
                $paths = max(1, (int) DB::table('ssr_metrics')
                    ->whereNotNull($timestampColumn)
                    ->distinct()
                    ->count('path'));
            }
        } else {
            $fallback = $this->loadSsrFallback();

            if ($fallback !== []) {
                $uniquePaths = [];
                $totalScore = 0;
                $count = 0;

                foreach ($fallback as $record) {
                    if (isset($record['path'])) {
                        $uniquePaths[(string) $record['path']] = true;
                    }

                    if (isset($record['score'])) {
                        $totalScore += (int) $record['score'];
                        $count++;
                    }
                }

                $paths = count($uniquePaths);

                if ($count > 0) {
                    $score = (int) round($totalScore / $count);
                }
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
            $dateExpression = 'date('.$timestampColumn.')';

            $rows = DB::table('ssr_metrics')
                ->selectRaw($dateExpression.' as d, avg(score) as s')
                ->whereNotNull($timestampColumn)
                ->groupBy('d')
                ->orderByDesc('d')
                ->limit($limit)
                ->get()
                ->sortBy('d')
                ->values();

            foreach ($rows as $row) {
                $labels[] = $row->d;
                $series[] = round((float) $row->s, 2);
            }
        } else {
            $fallback = $this->loadSsrFallback();

            if ($fallback !== []) {
                $grouped = [];

                foreach ($fallback as $record) {
                    if (! isset($record['score'])) {
                        continue;
                    }

                    $date = $this->resolveFallbackDate($record);

                    if ($date === null) {
                        continue;
                    }

                    if (! isset($grouped[$date])) {
                        $grouped[$date] = ['sum' => 0.0, 'count' => 0];
                    }

                    $grouped[$date]['sum'] += (float) $record['score'];
                    $grouped[$date]['count']++;
                }

                if ($grouped !== []) {
                    ksort($grouped);

                    $processed = 0;

                    foreach ($grouped as $date => $values) {
                        if ($processed >= $limit) {
                            break;
                        }

                        $labels[] = $date;
                        $series[] = round($values['sum'] / max(1, $values['count']), 2);
                        $processed++;
                    }
                }
            }
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadSsrFallback(): array
    {
        if (! Storage::exists('metrics/ssr.jsonl')) {
            return [];
        }

        $content = trim((string) Storage::get('metrics/ssr.jsonl'));

        if ($content === '') {
            return [];
        }

        $records = [];
        $lines = preg_split("/\r?\n/", $content) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolveFallbackDate(array $record): ?string
    {
        $timestamp = $record['ts']
            ?? $record['timestamp']
            ?? $record['collected_at']
            ?? null;

        if ($timestamp === null) {
            return null;
        }

        try {
            return Carbon::parse((string) $timestamp)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
