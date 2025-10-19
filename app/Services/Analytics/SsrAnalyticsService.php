<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\SsrMetric;
use Carbon\CarbonImmutable;
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
        } else {
            $records = $this->loadSsrJsonlFallback();

            if ($records !== []) {
                $total = 0;

                foreach ($records as $record) {
                    if (! is_array($record)) {
                        continue;
                    }

                    $value = $record['score'] ?? null;
                    if ($value === null || ! is_numeric($value)) {
                        continue;
                    }

                    $total += (float) $value;
                    $paths++;
                }

                if ($paths > 0) {
                    $score = (int) round($total / $paths);
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
            $rows = DB::table('ssr_metrics')
                ->selectRaw('date(created_at) as d, avg(score) as s')
                ->groupBy('d')
                ->orderBy('d')
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $labels[] = $row->d;
                $series[] = round((float) $row->s, 2);
            }
        } else {
            $records = $this->loadSsrJsonlFallback();

            if ($records !== []) {
                $grouped = [];

                foreach ($records as $record) {
                    if (! is_array($record)) {
                        continue;
                    }

                    $timestamp = $record['ts'] ?? null;
                    $score = $record['score'] ?? null;

                    if (! is_string($timestamp) || $timestamp === '' || ! is_numeric($score)) {
                        continue;
                    }

                    try {
                        $date = CarbonImmutable::parse($timestamp)->toDateString();
                    } catch (\Throwable) {
                        continue;
                    }

                    if (! isset($grouped[$date])) {
                        $grouped[$date] = ['sum' => 0.0, 'count' => 0];
                    }

                    $grouped[$date]['sum'] += (float) $score;
                    $grouped[$date]['count']++;
                }

                if ($grouped !== []) {
                    ksort($grouped);

                    if (count($grouped) > $limit) {
                        $grouped = array_slice($grouped, -$limit, null, true);
                    }

                    foreach ($grouped as $date => $aggregate) {
                        $labels[] = $date;
                        $average = $aggregate['count'] > 0 ? $aggregate['sum'] / $aggregate['count'] : 0.0;
                        $series[] = round($average, 2);
                    }
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

        return SsrMetric::query()
            ->fromSub(function ($query) use ($today, $yesterday): void {
                $query
                    ->fromSub(function ($aggregateQuery) use ($today, $yesterday): void {
                        $aggregateQuery
                            ->from('ssr_metrics')
                            ->selectRaw('path, date(created_at) as d, avg(score) as avg_score')
                            ->whereIn(DB::raw('date(created_at)'), [$yesterday, $today])
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
            return $this->dropRowsFromFallback($limit);
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadSsrJsonlFallback(): array
    {
        if (Storage::exists('metrics/ssr.jsonl')) {
            $content = trim((string) Storage::get('metrics/ssr.jsonl'));
            if ($content !== '') {
                $lines = preg_split("/\r?\n/", $content) ?: [];
                $records = [];

                foreach ($lines as $line) {
                    if ($line === '') {
                        continue;
                    }

                    $decoded = json_decode($line, true);
                    if (is_array($decoded)) {
                        $records[] = $decoded;
                    }
                }

                if ($records !== []) {
                    return $records;
                }
            }
        }

        return [];
    }

    /**
     * @return array<int, array{path: string, score_today: float, score_yesterday: float, delta: float}>
     */
    private function dropRowsFromFallback(int $limit): array
    {
        $records = $this->loadSsrJsonlFallback();

        if ($records === []) {
            return [];
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $aggregated = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $path = $record['path'] ?? null;
            $score = $record['score'] ?? null;
            $timestamp = $record['ts'] ?? null;

            if (! is_string($path) || $path === '' || ! is_numeric($score) || ! is_string($timestamp) || $timestamp === '') {
                continue;
            }

            try {
                $date = CarbonImmutable::parse($timestamp)->toDateString();
            } catch (\Throwable) {
                continue;
            }

            if ($date !== $today && $date !== $yesterday) {
                continue;
            }

            if (! isset($aggregated[$path][$date])) {
                $aggregated[$path][$date] = ['sum' => 0.0, 'count' => 0];
            }

            $aggregated[$path][$date]['sum'] += (float) $score;
            $aggregated[$path][$date]['count']++;
        }

        $rows = [];

        foreach ($aggregated as $path => $data) {
            $todayAverage = $this->averageFromAggregate($data[$today] ?? null);
            $yesterdayAverage = $this->averageFromAggregate($data[$yesterday] ?? null);

            if ($todayAverage === 0.0 && $yesterdayAverage === 0.0) {
                continue;
            }

            $delta = round($todayAverage - $yesterdayAverage, 2);

            if ($delta >= 0.0) {
                continue;
            }

            $rows[] = [
                'path' => $path,
                'score_today' => round($todayAverage, 2),
                'score_yesterday' => round($yesterdayAverage, 2),
                'delta' => $delta,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['delta'] === $b['delta']) {
                return $a['path'] <=> $b['path'];
            }

            return $a['delta'] <=> $b['delta'];
        });

        if ($limit < count($rows)) {
            $rows = array_slice($rows, 0, $limit);
        }

        return $rows;
    }

    private function averageFromAggregate(?array $aggregate): float
    {
        if ($aggregate === null) {
            return 0.0;
        }

        $sum = (float) ($aggregate['sum'] ?? 0.0);
        $count = (int) ($aggregate['count'] ?? 0);

        if ($count === 0) {
            return 0.0;
        }

        return $sum / $count;
    }
}
