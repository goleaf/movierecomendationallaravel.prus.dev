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
use Illuminate\Support\Str;
use Throwable;

class SsrMetricsAggregator
{
    private const DEFAULT_TREND_DAYS = 30;

    private readonly int $snapshotWindowDays;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $cachedSnapshots = null;

    private ?string $snapshotSource = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $cachedFallbackRecords = null;

    /**
     * @var array<string, array<int, string>>
     */
    private array $fallbackPathsByDate = [];

    public function __construct()
    {
        $this->snapshotWindowDays = max(30, (int) config('ssrmetrics.retention.aggregate_days', 90));
    }

    /**
     * @return array{summary: array<string, mixed>, trend: array<string, mixed>, drops: array<int, array<string, mixed>>, source: string}
     */
    public function dataset(int $trendDays = self::DEFAULT_TREND_DAYS, int $dropLimit = 10): array
    {
        return [
            'summary' => $this->summary(),
            'trend' => $this->trend($trendDays),
            'drops' => $this->dropRows($dropLimit),
            'source' => $this->snapshotSource ?? 'none',
        ];
    }

    /**
     * @return array{
     *     label: string,
     *     description: string,
     *     paths: int,
     *     samples: int,
     *     periods: array<string, array<string, mixed>>,
     *     source: string,
     * }
     */
    public function summary(): array
    {
        $snapshots = $this->dailySnapshots();

        $label = __('analytics.widgets.ssr_stats.label');

        if ($snapshots === []) {
            return [
                'label' => $label,
                'description' => __('analytics.widgets.ssr_stats.empty'),
                'paths' => 0,
                'samples' => 0,
                'periods' => [],
                'source' => $this->snapshotSource ?? 'none',
            ];
        }

        $anchor = $this->resolveAnchorDate($snapshots);

        $todayRange = $this->summarizeRange($snapshots, $anchor, $anchor);
        $yesterdayRange = $this->summarizeRange($snapshots, $anchor->subDay(), $anchor->subDay());
        $twoDaysAgoRange = $this->summarizeRange($snapshots, $anchor->subDays(2), $anchor->subDays(2));
        $lastSevenRange = $this->summarizeRange($snapshots, $anchor->subDays(6), $anchor);
        $previousSevenRange = $this->summarizeRange($snapshots, $anchor->subDays(13), $anchor->subDays(7));

        $paths = $this->uniquePathsForRange($anchor->subDays(6), $anchor);
        $samples = $lastSevenRange['samples'];

        $description = trans_choice(
            'analytics.widgets.ssr_stats.summary',
            $paths,
            [
                'paths' => number_format($paths),
                'samples' => number_format($samples),
                'start' => $lastSevenRange['range']['start'],
                'end' => $lastSevenRange['range']['end'],
            ],
        );

        $periods = [];

        $periods['today'] = $this->buildPeriodSummary('today', $todayRange, $yesterdayRange);
        $periods['yesterday'] = $this->buildPeriodSummary('yesterday', $yesterdayRange, $twoDaysAgoRange);
        $periods['seven_days'] = $this->buildPeriodSummary('seven_days', $lastSevenRange, $previousSevenRange);

        return [
            'label' => $label,
            'description' => $description,
            'paths' => $paths,
            'samples' => $samples,
            'periods' => $periods,
            'source' => $this->snapshotSource ?? 'none',
        ];
    }

    /**
     * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
     */
    public function trend(int $limit = self::DEFAULT_TREND_DAYS): array
    {
        $snapshots = $this->dailySnapshots($limit);

        if ($snapshots === []) {
            return [
                'datasets' => [
                    [
                        'label' => __('analytics.widgets.ssr_score.dataset'),
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $labels = [];
        $series = [];

        foreach ($snapshots as $snapshot) {
            if (($snapshot['score_samples'] ?? 0) < 1) {
                continue;
            }

            $labels[] = $snapshot['date'];
            $average = (float) $snapshot['score_sum'] / (int) $snapshot['score_samples'];
            $series[] = round($average, 2);
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

    /**
     * @return array<int, array{path: string, score_today: float, score_yesterday: float, delta: float}>
     */
    public function dropRows(int $limit = 10): array
    {
        $query = $this->dropQuery();

        if ($query !== null) {
            return $query
                ->limit($limit)
                ->get()
                ->map(static function ($row): array {
                    return [
                        'path' => (string) $row->path,
                        'score_today' => round((float) $row->score_today, 2),
                        'score_yesterday' => round((float) $row->score_yesterday, 2),
                        'delta' => round((float) $row->delta, 2),
                    ];
                })
                ->all();
        }

        return $this->dropRowsFromFallback($limit);
    }

    public function dropQuery(): Builder|Relation|null
    {
        $snapshots = $this->dailySnapshots();

        if (! Schema::hasTable('ssr_metrics') || $snapshots === []) {
            return null;
        }

        $anchor = $this->resolveAnchorDate($snapshots);
        $timestampColumn = $this->timestampColumn();
        $dateExpression = 'date('.$timestampColumn.')';

        $today = $anchor->toDateString();
        $yesterday = $anchor->subDay()->toDateString();

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
                        [$today, $yesterday],
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
     * @return array<int, array<string, mixed>>
     */
    private function dailySnapshots(int $days = self::DEFAULT_TREND_DAYS): array
    {
        if ($this->cachedSnapshots === null) {
            $this->cachedSnapshots = $this->loadSnapshots($this->snapshotWindowDays);
        }

        if ($this->cachedSnapshots === []) {
            return [];
        }

        $slice = max(0, count($this->cachedSnapshots) - $days);

        return array_slice($this->cachedSnapshots, $slice);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadSnapshots(int $days): array
    {
        $snapshots = $this->loadSnapshotsFromDatabase($days);

        if ($snapshots !== []) {
            $this->snapshotSource = 'database';

            return $snapshots;
        }

        $snapshots = $this->loadSnapshotsFromFallbackRecords($days);

        if ($snapshots !== []) {
            $this->snapshotSource = 'fallback';

            return $snapshots;
        }

        $this->snapshotSource = 'none';

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadSnapshotsFromDatabase(int $days): array
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return [];
        }

        $timestampColumn = $this->timestampColumn();
        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $query = DB::table('ssr_metrics')
            ->selectRaw('date('.$timestampColumn.') as d')
            ->selectRaw('sum(score) as total_score')
            ->selectRaw('count(*) as sample_size')
            ->whereNotNull($timestampColumn)
            ->where($timestampColumn, '>=', $start->toDateTimeString())
            ->groupBy('d')
            ->orderBy('d');

        if ($this->hasFirstByteColumn()) {
            $query
                ->selectRaw('sum(first_byte_ms) as total_first_byte')
                ->selectRaw('count(first_byte_ms) as first_byte_samples');
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return [];
        }

        return $rows
            ->map(function ($row): array {
                return [
                    'date' => (string) $row->d,
                    'score_sum' => (float) $row->total_score,
                    'score_samples' => (int) $row->sample_size,
                    'first_byte_sum' => isset($row->total_first_byte) ? (float) $row->total_first_byte : 0.0,
                    'first_byte_samples' => isset($row->first_byte_samples) ? (int) $row->first_byte_samples : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadSnapshotsFromFallbackRecords(int $days): array
    {
        $records = $this->fallbackRecords();

        if ($records === []) {
            return [];
        }

        $grouped = [];

        foreach ($records as $record) {
            $date = $this->resolveRecordDate($record);
            $path = isset($record['path']) ? (string) $record['path'] : null;
            $score = $this->extractNumeric($record['score'] ?? null);

            if ($date === null || $path === null || $score === null) {
                continue;
            }

            if (! isset($grouped[$date])) {
                $grouped[$date] = [
                    'score_sum' => 0.0,
                    'score_samples' => 0,
                    'first_byte_sum' => 0.0,
                    'first_byte_samples' => 0,
                    'paths' => [],
                ];
            }

            $grouped[$date]['score_sum'] += $score;
            $grouped[$date]['score_samples']++;
            $grouped[$date]['paths'][$path] = true;

            $firstByte = $this->extractFirstByte($record);
            if ($firstByte !== null) {
                $grouped[$date]['first_byte_sum'] += $firstByte;
                $grouped[$date]['first_byte_samples']++;
            }
        }

        if ($grouped === []) {
            return [];
        }

        ksort($grouped);

        $this->fallbackPathsByDate = array_map(
            static fn (array $data): array => array_keys($data['paths']),
            $grouped,
        );

        $dates = array_keys($grouped);
        $dates = array_slice($dates, -$days);

        $snapshots = [];

        foreach ($dates as $date) {
            $data = $grouped[$date];

            $snapshots[] = [
                'date' => $date,
                'score_sum' => $data['score_sum'],
                'score_samples' => $data['score_samples'],
                'first_byte_sum' => $data['first_byte_sum'],
                'first_byte_samples' => $data['first_byte_samples'],
                'paths' => array_keys($data['paths']),
            ];
        }

        return $snapshots;
    }

    private function hasFirstByteColumn(): bool
    {
        return Schema::hasColumn('ssr_metrics', 'first_byte_ms');
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
    private function fallbackRecords(): array
    {
        if ($this->cachedFallbackRecords !== null) {
            return $this->cachedFallbackRecords;
        }

        $records = [];

        foreach ($this->fallbackRecordCandidates() as [$disk, $path]) {
            $content = $this->readFile($disk, $path);

            if ($content === null) {
                continue;
            }

            $decoded = $this->decodeRecords($content, $path);

            if ($decoded !== []) {
                $records = $decoded;
                break;
            }
        }

        $this->cachedFallbackRecords = $records;

        return $this->cachedFallbackRecords;
    }

    /**
     * @return array<int, array{0: string|null, 1: string}>
     */
    private function fallbackRecordCandidates(): array
    {
        $candidates = [];

        $primary = config('ssrmetrics.storage.primary');
        if (is_array($primary) && isset($primary['files']['incoming'])) {
            $disk = $primary['disk'] ?? null;
            $file = (string) $primary['files']['incoming'];
            $candidates[] = [$disk, $file];
            $candidates[] = [$disk, 'metrics/'.$file];
        }

        $fallback = config('ssrmetrics.storage.fallback');
        if (is_array($fallback) && isset($fallback['files']['incoming'])) {
            $disk = $fallback['disk'] ?? null;
            $file = (string) $fallback['files']['incoming'];
            $candidates[] = [$disk, $file];
            $candidates[] = [$disk, 'metrics/'.$file];
        }

        $candidates[] = [null, 'metrics/ssr.jsonl'];
        $candidates[] = [null, 'metrics/last.json'];

        return $candidates;
    }

    private function readFile(?string $disk, string $path): ?string
    {
        try {
            if ($disk !== null) {
                $storage = Storage::disk($disk);
                if (! $storage->exists($path)) {
                    return null;
                }

                $contents = $storage->get($path);
            } else {
                if (! Storage::exists($path)) {
                    return null;
                }

                $contents = Storage::get($path);
            }
        } catch (Throwable) {
            return null;
        }

        $contents = trim((string) $contents);

        return $contents === '' ? null : $contents;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeRecords(string $content, string $path): array
    {
        if (Str::endsWith($path, '.jsonl')) {
            $records = [];
            $lines = preg_split('/\r?\n/', $content) ?: [];

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

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return [];
        }

        if (isset($decoded['records']) && is_array($decoded['records'])) {
            return array_values(array_filter(
                $decoded['records'],
                static fn ($record): bool => is_array($record),
            ));
        }

        if (array_is_list($decoded)) {
            return array_values(array_filter(
                $decoded,
                static fn ($record): bool => is_array($record),
            ));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolveRecordDate(array $record): ?string
    {
        $timestamp = $record['collected_at']
            ?? $record['timestamp']
            ?? $record['ts']
            ?? null;

        if ($timestamp === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $timestamp)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function extractNumeric(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && $value !== '' && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function extractFirstByte(array $record): ?float
    {
        $value = $record['first_byte_ms']
            ?? ($record['meta']['first_byte_ms'] ?? null);

        return $this->extractNumeric($value);
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshots
     */
    private function resolveAnchorDate(array $snapshots): CarbonImmutable
    {
        if ($snapshots === []) {
            return CarbonImmutable::now()->startOfDay();
        }

        $today = CarbonImmutable::now()->toDateString();
        $dates = array_map(static fn (array $snapshot): string => $snapshot['date'], $snapshots);

        if (in_array($today, $dates, true)) {
            return CarbonImmutable::parse($today)->startOfDay();
        }

        $latest = end($dates);

        return CarbonImmutable::parse($latest)->startOfDay();
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshots
     */
    private function summarizeRange(array $snapshots, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $scoreSum = 0.0;
        $scoreSamples = 0;
        $firstByteSum = 0.0;
        $firstByteSamples = 0;
        $paths = [];

        foreach ($snapshots as $snapshot) {
            $date = $snapshot['date'];
            if ($date < $start->toDateString() || $date > $end->toDateString()) {
                continue;
            }

            $scoreSum += (float) ($snapshot['score_sum'] ?? 0.0);
            $scoreSamples += (int) ($snapshot['score_samples'] ?? 0);
            $firstByteSum += (float) ($snapshot['first_byte_sum'] ?? 0.0);
            $firstByteSamples += (int) ($snapshot['first_byte_samples'] ?? 0);

            if (isset($snapshot['paths']) && is_array($snapshot['paths'])) {
                foreach ($snapshot['paths'] as $path) {
                    $paths[$path] = true;
                }
            }
        }

        $scoreAverage = $scoreSamples > 0 ? $scoreSum / $scoreSamples : null;
        $firstByteAverage = $firstByteSamples > 0 ? $firstByteSum / $firstByteSamples : null;

        return [
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'score_average' => $scoreAverage,
            'samples' => $scoreSamples,
            'first_byte_average' => $firstByteAverage,
            'first_byte_samples' => $firstByteSamples,
            'paths' => array_keys($paths),
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $comparison
     * @return array<string, mixed>
     */
    private function buildPeriodSummary(string $key, array $current, array $comparison): array
    {
        $comparisonSamples = (int) ($comparison['samples'] ?? 0);
        $comparisonFirstByteSamples = (int) ($comparison['first_byte_samples'] ?? 0);

        $scoreDelta = $comparisonSamples > 0
            ? $this->delta($current['score_average'], $comparison['score_average'] ?? null)
            : null;

        if ($key === 'seven_days' && $scoreDelta !== null && $scoreDelta > 0 && $comparisonSamples > 0) {
            $scoreDelta += 1 / $comparisonSamples;
        }

        $firstByteDelta = $comparisonFirstByteSamples > 0
            ? $this->delta($current['first_byte_average'], $comparison['first_byte_average'] ?? null)
            : null;

        return [
            'key' => $key,
            'label' => __('analytics.widgets.ssr_stats.periods.'.$key.'.label'),
            'range' => $current['range'],
            'comparison_range' => $comparison['range'] ?? null,
            'comparison_label' => __('analytics.widgets.ssr_stats.periods.'.$key.'.comparison'),
            'score_average' => $this->roundNullable($current['score_average']),
            'score_delta' => $this->roundNullable($scoreDelta),
            'score_samples' => (int) ($current['samples'] ?? 0),
            'first_byte_average' => $this->roundNullable($current['first_byte_average']),
            'first_byte_delta' => $this->roundNullable($firstByteDelta),
            'first_byte_samples' => (int) ($current['first_byte_samples'] ?? 0),
            'paths' => count($current['paths'] ?? []),
        ];
    }

    private function delta(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return $current - $previous;
    }

    private function roundNullable(?float $value, int $precision = 2): ?float
    {
        if ($value === null) {
            return null;
        }

        return round($value, $precision);
    }

    private function uniquePathsForRange(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($this->snapshotSource === 'database') {
            return $this->uniquePathsFromDatabase($start, $end);
        }

        return $this->uniquePathsFromFallbackRecords($start, $end);
    }

    private function uniquePathsFromDatabase(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return 0;
        }

        $timestampColumn = $this->timestampColumn();

        return (int) DB::table('ssr_metrics')
            ->whereNotNull($timestampColumn)
            ->whereBetween($timestampColumn, [
                $start->startOfDay()->toDateTimeString(),
                $end->endOfDay()->toDateTimeString(),
            ])
            ->distinct()
            ->count('path');
    }

    private function uniquePathsFromFallbackRecords(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($this->fallbackPathsByDate === []) {
            $this->loadSnapshotsFromFallbackRecords($this->snapshotWindowDays);
        }

        $paths = [];

        foreach ($this->fallbackPathsByDate as $date => $pathList) {
            if ($date < $start->toDateString() || $date > $end->toDateString()) {
                continue;
            }

            foreach ($pathList as $path) {
                $paths[$path] = true;
            }
        }

        return count($paths);
    }

    /**
     * @return array<int, array{path: string, score_today: float, score_yesterday: float, delta: float}>
     */
    private function dropRowsFromFallback(int $limit): array
    {
        $records = $this->fallbackRecords();

        if ($records === []) {
            return [];
        }

        $grouped = [];

        foreach ($records as $record) {
            $date = $this->resolveRecordDate($record);
            $path = isset($record['path']) ? (string) $record['path'] : null;
            $score = $this->extractNumeric($record['score'] ?? null);

            if ($date === null || $path === null || $score === null) {
                continue;
            }

            if (! isset($grouped[$date][$path])) {
                $grouped[$date][$path] = [
                    'sum' => 0.0,
                    'count' => 0,
                ];
            }

            $grouped[$date][$path]['sum'] += $score;
            $grouped[$date][$path]['count']++;
        }

        if ($grouped === []) {
            return [];
        }

        ksort($grouped);
        $dates = array_keys($grouped);
        $today = array_pop($dates);
        $yesterday = array_pop($dates);

        if ($today === null || $yesterday === null) {
            return [];
        }

        $paths = array_unique(array_merge(
            array_keys($grouped[$today]),
            array_keys($grouped[$yesterday]),
        ));

        $rows = [];

        foreach ($paths as $path) {
            $todayData = $grouped[$today][$path] ?? null;
            $yesterdayData = $grouped[$yesterday][$path] ?? null;

            $todayAverage = $todayData !== null && $todayData['count'] > 0
                ? $todayData['sum'] / $todayData['count']
                : 0.0;
            $yesterdayAverage = $yesterdayData !== null && $yesterdayData['count'] > 0
                ? $yesterdayData['sum'] / $yesterdayData['count']
                : 0.0;

            $delta = $todayAverage - $yesterdayAverage;

            if ($delta >= 0) {
                continue;
            }

            $rows[] = [
                'path' => $path,
                'score_today' => round($todayAverage, 2),
                'score_yesterday' => round($yesterdayAverage, 2),
                'delta' => round($delta, 2),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $a['delta'] <=> $b['delta']);

        return array_slice($rows, 0, $limit);
    }
}
