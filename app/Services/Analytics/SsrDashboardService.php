<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SsrDashboardService
{
    private const SUMMARY_LOOKBACK_DAYS = 7;

    public const TREND_LOOKBACK_DAYS = 30;

    private const JSONL_SAMPLE_SIZE = 200;

    /**
     * @return array{
     *     summary: array<string, float|int|null>,
     *     trend: array{points: array<int, array{date: string, score: float}>},
     *     drops: array<int, array{path: string, today: float, yesterday: float, delta: float}>,
     *     source: string,
     *     last_updated: CarbonImmutable|null
     * }
     */
    public function overview(): array
    {
        if (Schema::hasTable('ssr_metrics')) {
            return $this->overviewFromDatabase();
        }

        if (Storage::exists('metrics/ssr.jsonl')) {
            return $this->overviewFromJsonl();
        }

        return [
            'summary' => [
                'average_score' => null,
                'path_count' => 0,
            ],
            'trend' => [
                'points' => [],
            ],
            'drops' => [],
            'source' => 'empty',
            'last_updated' => null,
        ];
    }

    private function overviewFromDatabase(): array
    {
        $now = CarbonImmutable::now();
        $lookbackStart = $now->subDays(self::SUMMARY_LOOKBACK_DAYS);

        $summarySelects = [
            'avg(score) as average_score',
            'count(distinct path) as path_count',
        ];

        $optionalColumns = [
            'size' => 'avg_html_size',
            'meta_count' => 'avg_meta_tags',
            'og_count' => 'avg_og_tags',
            'ldjson_count' => 'avg_ldjson_blocks',
            'blocking_scripts' => 'avg_blocking_scripts',
            'first_byte_ms' => 'avg_first_byte_ms',
        ];

        foreach ($optionalColumns as $column => $alias) {
            if (Schema::hasColumn('ssr_metrics', $column)) {
                $summarySelects[] = sprintf('avg(%s) as %s', $column, $alias);
            }
        }

        /** @var object|null $row */
        $row = DB::table('ssr_metrics')
            ->selectRaw(implode(', ', $summarySelects))
            ->where('created_at', '>=', $lookbackStart)
            ->first();

        $summary = [
            'average_score' => $row?->average_score !== null ? round((float) $row->average_score, 2) : null,
            'path_count' => $row?->path_count !== null ? (int) $row->path_count : 0,
        ];

        if ($row) {
            foreach ($optionalColumns as $alias) {
                if (property_exists($row, $alias) && $row->{$alias} !== null) {
                    $summary[$alias] = round((float) $row->{$alias}, $alias === 'avg_first_byte_ms' ? 0 : 2);
                }
            }

            if (isset($summary['avg_html_size'])) {
                $summary['avg_html_size'] = round($summary['avg_html_size'] / 1024, 1);
            }
        }

        $trendPoints = DB::table('ssr_metrics')
            ->selectRaw('date(created_at) as date, avg(score) as avg_score')
            ->where('created_at', '>=', $now->subDays(self::TREND_LOOKBACK_DAYS))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($record) => [
                'date' => (string) $record->date,
                'score' => round((float) $record->avg_score, 2),
            ])
            ->all();

        /** @var string|null $lastUpdated */
        $lastUpdated = DB::table('ssr_metrics')
            ->selectRaw('max(created_at) as ts')
            ->value('ts');

        $drops = $this->buildDropsFromDatabase($now);

        return [
            'summary' => $summary,
            'trend' => ['points' => $trendPoints],
            'drops' => $drops,
            'source' => 'database',
            'last_updated' => $lastUpdated ? CarbonImmutable::parse($lastUpdated) : null,
        ];
    }

    private function overviewFromJsonl(): array
    {
        $now = CarbonImmutable::now();
        $lookbackStart = $now->subDays(self::SUMMARY_LOOKBACK_DAYS);
        $content = trim((string) Storage::get('metrics/ssr.jsonl'));

        if ($content === '') {
            return [
                'summary' => [
                    'average_score' => null,
                    'path_count' => 0,
                ],
                'trend' => ['points' => []],
                'drops' => [],
                'source' => 'jsonl',
                'last_updated' => null,
            ];
        }

        $lines = array_filter(explode("\n", $content));
        $lines = array_slice($lines, -self::JSONL_SAMPLE_SIZE);

        $records = collect($lines)
            ->map(function (string $line): ?array {
                try {
                    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    return null;
                }

                return is_array($decoded) ? $decoded : null;
            })
            ->filter()
            ->values();

        if ($records->isEmpty()) {
            return [
                'summary' => [
                    'average_score' => null,
                    'path_count' => 0,
                ],
                'trend' => ['points' => []],
                'drops' => [],
                'source' => 'jsonl',
                'last_updated' => null,
            ];
        }

        $recent = $records->filter(function (array $row) use ($lookbackStart): bool {
            $timestamp = $this->parseTimestamp($row['ts'] ?? null);

            return $timestamp === null || $timestamp->greaterThanOrEqualTo($lookbackStart);
        });

        $averageScore = $recent->avg(fn (array $row): ?float => isset($row['score']) ? (float) $row['score'] : null);
        $pathCount = $recent->pluck('path')->filter()->unique()->count();
        $avgHtmlSize = $recent->avg(function (array $row): ?float {
            return isset($row['html_size']) ? (float) $row['html_size'] : (isset($row['size']) ? (float) $row['size'] : null);
        });
        $avgMeta = $recent->avg(fn (array $row): ?float => isset($row['meta_count']) ? (float) $row['meta_count'] : null);
        $avgOg = $recent->avg(fn (array $row): ?float => isset($row['og']) ? (float) $row['og'] : (isset($row['og_count']) ? (float) $row['og_count'] : null));
        $avgLd = $recent->avg(fn (array $row): ?float => isset($row['ld']) ? (float) $row['ld'] : (isset($row['ldjson_count']) ? (float) $row['ldjson_count'] : null));
        $avgBlocking = $recent->avg(fn (array $row): ?float => isset($row['blocking']) ? (float) $row['blocking'] : (isset($row['blocking_scripts']) ? (float) $row['blocking_scripts'] : null));
        $avgFirstByte = $recent->avg(fn (array $row): ?float => isset($row['first_byte_ms']) ? (float) $row['first_byte_ms'] : null);

        $summary = [
            'average_score' => $averageScore !== null ? round($averageScore, 2) : null,
            'path_count' => $pathCount,
        ];

        if ($avgHtmlSize !== null) {
            $summary['avg_html_size'] = round($avgHtmlSize / 1024, 1);
        }

        if ($avgMeta !== null) {
            $summary['avg_meta_tags'] = round($avgMeta, 2);
        }

        if ($avgOg !== null) {
            $summary['avg_og_tags'] = round($avgOg, 2);
        }

        if ($avgLd !== null) {
            $summary['avg_ldjson_blocks'] = round($avgLd, 2);
        }

        if ($avgBlocking !== null) {
            $summary['avg_blocking_scripts'] = round($avgBlocking, 2);
        }

        if ($avgFirstByte !== null) {
            $summary['avg_first_byte_ms'] = round($avgFirstByte, 0);
        }

        $trendPoints = $this->buildTrendFromRecords($records, $now);
        $drops = $this->buildDropsFromRecords($records, $now);

        $lastUpdated = $records
            ->map(fn (array $row): ?CarbonImmutable => $this->parseTimestamp($row['ts'] ?? null))
            ->filter()
            ->sort()
            ->last();

        return [
            'summary' => $summary,
            'trend' => ['points' => $trendPoints],
            'drops' => $drops,
            'source' => 'jsonl',
            'last_updated' => $lastUpdated,
        ];
    }

    /**
     * @return array<int, array{path: string, today: float, yesterday: float, delta: float}>
     */
    private function buildDropsFromDatabase(CarbonImmutable $now): array
    {
        $yesterday = $now->subDay()->toDateString();
        $today = $now->toDateString();

        return DB::query()
            ->fromSub(function ($query) use ($today, $yesterday) {
                $query
                    ->fromSub(function ($aggregateQuery) use ($today, $yesterday) {
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
                'path',
                DB::raw('coalesce(score_today, 0) as score_today'),
                DB::raw('coalesce(score_yesterday, 0) as score_yesterday'),
                DB::raw('coalesce(score_today, 0) - coalesce(score_yesterday, 0) as delta'),
            ])
            ->whereRaw('(coalesce(score_today, 0) - coalesce(score_yesterday, 0)) < 0')
            ->orderBy('delta')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'path' => (string) $row->path,
                'today' => round((float) $row->score_today, 2),
                'yesterday' => round((float) $row->score_yesterday, 2),
                'delta' => round((float) $row->delta, 2),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @return array<int, array{date: string, score: float}>
     */
    private function buildTrendFromRecords(Collection $records, CarbonImmutable $now): array
    {
        $trendStart = $now->subDays(self::TREND_LOOKBACK_DAYS);

        $grouped = [];

        foreach ($records as $row) {
            $timestamp = $this->parseTimestamp($row['ts'] ?? null) ?? $now;

            if ($timestamp->lessThan($trendStart)) {
                continue;
            }

            $date = $timestamp->toDateString();
            $grouped[$date] ??= [];

            if (isset($row['score'])) {
                $grouped[$date][] = (float) $row['score'];
            }
        }

        ksort($grouped);

        $points = [];

        foreach ($grouped as $date => $scores) {
            if ($scores === []) {
                continue;
            }

            $points[] = [
                'date' => $date,
                'score' => round(array_sum($scores) / count($scores), 2),
            ];
        }

        return $points;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @return array<int, array{path: string, today: float, yesterday: float, delta: float}>
     */
    private function buildDropsFromRecords(Collection $records, CarbonImmutable $now): array
    {
        $today = $now->toDateString();
        $yesterday = $now->subDay()->toDateString();

        $daily = [];

        foreach ($records as $row) {
            if (! isset($row['path'], $row['score'])) {
                continue;
            }

            $timestamp = $this->parseTimestamp($row['ts'] ?? null) ?? $now;
            $date = $timestamp->toDateString();

            $daily[$date][$row['path']] ??= [];
            $daily[$date][$row['path']][] = (float) $row['score'];
        }

        $todayScores = [];

        foreach ($daily[$today] ?? [] as $path => $scores) {
            if ($scores === []) {
                continue;
            }

            $todayScores[$path] = array_sum($scores) / count($scores);
        }

        $yesterdayScores = [];

        foreach ($daily[$yesterday] ?? [] as $path => $scores) {
            if ($scores === []) {
                continue;
            }

            $yesterdayScores[$path] = array_sum($scores) / count($scores);
        }

        $drops = [];

        foreach ($todayScores as $path => $scoreToday) {
            if (! array_key_exists($path, $yesterdayScores)) {
                continue;
            }

            $scoreYesterday = $yesterdayScores[$path];
            $delta = $scoreToday - $scoreYesterday;

            if ($delta < 0) {
                $drops[] = [
                    'path' => $path,
                    'today' => round($scoreToday, 2),
                    'yesterday' => round($scoreYesterday, 2),
                    'delta' => round($delta, 2),
                ];
            }
        }

        usort($drops, static fn (array $a, array $b): int => $a['delta'] <=> $b['delta']);

        return array_slice($drops, 0, 10);
    }

    private function parseTimestamp(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
