<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SsrMetric;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SsrMetricsService
{
    /**
     * @var array<int, string>|null
     */
    private ?array $columnCache = null;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function persistSample(array $payload): void
    {
        $recordedAt = $this->resolveRecordedAt($payload['recorded_at'] ?? null);
        $meta = $this->normalizeMetaPayload($payload['meta'] ?? [], $payload);

        $normalizedPayload = $payload;
        $normalizedPayload['meta'] = $meta;

        if ($this->storeInDatabase($normalizedPayload, $recordedAt)) {
            return;
        }

        $this->storeInFallback($normalizedPayload, $recordedAt);
    }

    /**
     * @return array{label: string, score: int, paths: int, description: string}
     */
    public function headline(): array
    {
        $score = 0;
        $paths = 0;

        if ($this->tableExists()) {
            $timestampColumn = $this->timestampColumn();

            $row = DB::table('ssr_metrics')
                ->select(['score'])
                ->orderByDesc($timestampColumn)
                ->orderByDesc('id')
                ->first();

            if ($row !== null) {
                $score = (int) $row->score;
                $paths = 1;
            }
        } else {
            $records = $this->loadFallbackRecords();
            $paths = count($records);

            if ($paths > 0) {
                $total = 0;

                foreach ($records as $record) {
                    $total += (int) $record['score'];
                }

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

        if ($this->tableExists()) {
            $timestampColumn = $this->timestampColumn();
            $dateExpression = DB::raw(sprintf('date(%s)', $timestampColumn));

            $rows = DB::table('ssr_metrics')
                ->selectRaw('date('.$timestampColumn.') as d, avg(score) as s')
                ->groupBy($dateExpression)
                ->orderBy($dateExpression)
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $labels[] = (string) $row->d;
                $series[] = round((float) $row->s, 2);
            }
        } else {
            $records = $this->loadFallbackRecords();
            $buckets = [];

            foreach ($records as $record) {
                /** @var CarbonInterface $recordedAt */
                $recordedAt = $record['recorded_at'];
                $key = $recordedAt->toDateString();
                $buckets[$key] = $buckets[$key] ?? ['sum' => 0, 'count' => 0];
                $buckets[$key]['sum'] += (int) $record['score'];
                $buckets[$key]['count']++;
            }

            ksort($buckets);

            foreach (array_slice($buckets, -$limit, null, true) as $date => $bucket) {
                $labels[] = $date;
                $series[] = $bucket['count'] > 0 ? round($bucket['sum'] / $bucket['count'], 2) : 0.0;
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
        if (! $this->tableExists()) {
            return null;
        }

        $timestampColumn = $this->timestampColumn();
        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();
        $dateExpression = sprintf('date(%s)', $timestampColumn);

        return SsrMetric::query()
            ->fromSub(function ($query) use ($dateExpression, $today, $yesterday): void {
                $query
                    ->fromSub(function ($aggregateQuery) use ($dateExpression, $today, $yesterday): void {
                        $aggregateQuery
                            ->from('ssr_metrics')
                            ->selectRaw('path, '.$dateExpression.' as d, avg(score) as avg_score')
                            ->whereIn(DB::raw($dateExpression), [$yesterday, $today])
                            ->groupBy('path', DB::raw($dateExpression));
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
                    'path' => (string) $row->path,
                    'score_today' => (float) $row->score_today,
                    'score_yesterday' => (float) $row->score_yesterday,
                    'delta' => (float) $row->delta,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{path: string, avg_score: float, hints: array<int, string>}>
     */
    public function issues(int $lookbackDays = 2): array
    {
        $issues = [];
        $threshold = now()->subDays($lookbackDays);

        if ($this->tableExists()) {
            $timestampColumn = $this->timestampColumn();
            $select = $this->issueSelectColumns($timestampColumn);

            $rows = DB::table('ssr_metrics')
                ->select($select)
                ->where($timestampColumn, '>=', $threshold)
                ->get();

            $issues = $this->aggregateIssuesFromRows($rows->all());
        } else {
            $records = array_filter(
                $this->loadFallbackRecords(),
                static fn (array $record): bool => $record['recorded_at'] >= $threshold
            );
            $issues = $this->aggregateIssuesFromRows($records);
        }

        usort($issues, static fn (array $a, array $b): int => $a['avg_score'] <=> $b['avg_score']);

        return $issues;
    }

    /**
     * @return array<int, mixed>
     */
    private function issueSelectColumns(string $timestampColumn): array
    {
        $columns = $this->columns();

        $select = ['path', 'score'];

        if (in_array('meta', $columns, true)) {
            $select[] = 'meta';
        }

        if (in_array('first_byte_ms', $columns, true)) {
            $select[] = 'first_byte_ms';
        }

        foreach (['size', 'meta_count', 'og_count', 'ldjson_count', 'img_count', 'blocking_scripts'] as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            }
        }

        $select[] = DB::raw($timestampColumn.' as recorded_at');

        return $select;
    }

    /**
     * @param  iterable<int, mixed>  $rows
     * @return array<int, array{path: string, avg_score: float, hints: array<int, string>}>
     */
    private function aggregateIssuesFromRows(iterable $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $path = (string) (data_get($row, 'path') ?? '');

            if ($path === '') {
                continue;
            }

            $meta = $this->normalizeMetaPayload($this->extractMeta($row), (array) $row);
            $score = (float) (data_get($row, 'score', 0));

            if (! isset($grouped[$path])) {
                $grouped[$path] = [
                    'sum_score' => 0.0,
                    'count' => 0,
                    'blocking' => 0,
                    'ldjson' => 0,
                    'og' => 0,
                    'size' => 0,
                ];
            }

            $grouped[$path]['sum_score'] += $score;
            $grouped[$path]['count']++;
            $grouped[$path]['blocking'] += (int) ($meta['blocking_scripts'] ?? 0);
            $grouped[$path]['ldjson'] += (int) ($meta['ldjson_count'] ?? 0);
            $grouped[$path]['og'] += (int) ($meta['og_count'] ?? 0);
            $grouped[$path]['size'] += (int) ($meta['html_size'] ?? 0);
        }

        $issues = [];

        foreach ($grouped as $path => $data) {
            if ($data['count'] === 0) {
                continue;
            }

            $avgScore = $data['sum_score'] / $data['count'];
            $avgBlocking = $data['blocking'] / $data['count'];
            $avgLdjson = $data['ldjson'] / $data['count'];
            $avgOg = $data['og'] / $data['count'];
            $avgSize = $data['size'] / $data['count'];

            $hints = [];

            if ($avgBlocking > 0) {
                $hints[] = __('analytics.hints.ssr.add_defer');
            }

            if ($avgLdjson === 0.0) {
                $hints[] = __('analytics.hints.ssr.add_json_ld');
            }

            if ($avgOg < 3) {
                $hints[] = __('analytics.hints.ssr.expand_og');
            }

            if ($avgScore < 80 || $avgSize > 900 * 1024) {
                $hints[] = __('analytics.hints.ssr.reduce_payload');
            }

            $issues[] = [
                'path' => $path,
                'avg_score' => round($avgScore, 2),
                'hints' => array_values(array_unique($hints)),
            ];
        }

        return $issues;
    }

    private function storeInDatabase(array $payload, CarbonInterface $recordedAt): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        $columns = $this->columns();

        try {
            $data = [
                'path' => (string) $payload['path'],
                'score' => (int) $payload['score'],
            ];

            $meta = $payload['meta'];

            if (in_array('first_byte_ms', $columns, true)) {
                $data['first_byte_ms'] = (int) ($meta['first_byte_ms'] ?? $payload['first_byte_ms'] ?? 0);
            }

            if (in_array('meta', $columns, true)) {
                $data['meta'] = json_encode($meta, JSON_THROW_ON_ERROR);
            }

            $timestamp = $recordedAt->copy();

            if (in_array('recorded_at', $columns, true)) {
                $data['recorded_at'] = $timestamp;
            }

            if (in_array('created_at', $columns, true)) {
                $data['created_at'] = $timestamp;
            }

            if (in_array('updated_at', $columns, true)) {
                $data['updated_at'] = $timestamp;
            }

            $legacyMap = [
                'size' => 'html_size',
                'meta_count' => 'meta_count',
                'og_count' => 'og_count',
                'ldjson_count' => 'ldjson_count',
                'img_count' => 'img_count',
                'blocking_scripts' => 'blocking_scripts',
            ];

            foreach ($legacyMap as $column => $metaKey) {
                if (in_array($column, $columns, true) && array_key_exists($metaKey, $meta)) {
                    $data[$column] = $meta[$metaKey];
                }
            }

            DB::table('ssr_metrics')->insert($data);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Failed storing SSR metric in database, falling back to JSONL.', [
                'exception' => $exception,
            ]);

            return false;
        }
    }

    private function storeInFallback(array $payload, CarbonInterface $recordedAt): void
    {
        try {
            if (! Storage::exists('metrics')) {
                Storage::makeDirectory('metrics');
            }

            $meta = $payload['meta'];
            $timestamp = $recordedAt->toIso8601String();

            $record = [
                'recorded_at' => $timestamp,
                'ts' => $timestamp,
                'path' => (string) $payload['path'],
                'score' => (int) $payload['score'],
                'first_byte_ms' => (int) ($meta['first_byte_ms'] ?? $payload['first_byte_ms'] ?? 0),
                'meta' => $meta,
                'size' => $meta['html_size'] ?? null,
                'html_size' => $meta['html_size'] ?? null,
                'meta_count' => $meta['meta_count'] ?? null,
                'og' => $meta['og_count'] ?? null,
                'og_count' => $meta['og_count'] ?? null,
                'ld' => $meta['ldjson_count'] ?? null,
                'ldjson_count' => $meta['ldjson_count'] ?? null,
                'imgs' => $meta['img_count'] ?? null,
                'img_count' => $meta['img_count'] ?? null,
                'blocking' => $meta['blocking_scripts'] ?? null,
                'blocking_scripts' => $meta['blocking_scripts'] ?? null,
                'has_json_ld' => (bool) ($meta['has_json_ld'] ?? false),
                'has_open_graph' => (bool) ($meta['has_open_graph'] ?? false),
            ];

            Storage::append('metrics/ssr.jsonl', json_encode($record, JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            Log::error('Failed storing SSR metric.', [
                'exception' => $exception,
                'payload' => $payload,
            ]);
        }
    }

    /**
     * @return array<int, array{path: string, score: int, meta: array<string, mixed>, recorded_at: CarbonInterface}>
     */
    private function loadFallbackRecords(): array
    {
        $records = [];

        if (Storage::exists('metrics/ssr.jsonl')) {
            $content = trim((string) Storage::get('metrics/ssr.jsonl'));

            if ($content !== '') {
                $lines = preg_split('/\r?\n/', $content) ?: [];

                foreach ($lines as $line) {
                    if ($line === '') {
                        continue;
                    }

                    $decoded = json_decode($line, true);

                    if (is_array($decoded)) {
                        $records[] = $this->normalizeRecord($decoded);
                    }
                }
            }
        }

        if ($records === [] && Storage::exists('metrics/last.json')) {
            $decoded = json_decode((string) Storage::get('metrics/last.json'), true);

            if (is_array($decoded)) {
                foreach ($decoded as $record) {
                    if (is_array($record)) {
                        $records[] = $this->normalizeRecord($record);
                    }
                }
            }
        }

        return array_values(array_filter($records, static fn (?array $record) => $record !== null));
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{path: string, score: int, meta: array<string, mixed>, recorded_at: CarbonInterface}|null
     */
    private function normalizeRecord(array $record): ?array
    {
        $path = (string) ($record['path'] ?? '');

        if ($path === '') {
            return null;
        }

        $recordedAt = $this->resolveRecordedAt($record['recorded_at'] ?? ($record['ts'] ?? null));
        $meta = $this->normalizeMetaPayload($record['meta'] ?? [], $record);

        return [
            'path' => $path,
            'score' => (int) ($record['score'] ?? 0),
            'meta' => $meta,
            'recorded_at' => $recordedAt,
        ];
    }

    /**
     * @param  mixed  $value
     */
    private function resolveRecordedAt($value): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                // Fall through to now()
            }
        }

        return now();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeMetaPayload(array $meta, array $payload): array
    {
        $defaults = [
            'first_byte_ms' => data_get($payload, 'first_byte_ms', data_get($meta, 'first_byte_ms', data_get($payload, 'meta.first_byte_ms'))),
            'html_size' => data_get($payload, 'html_size', data_get($meta, 'html_size', data_get($payload, 'size'))),
            'meta_count' => data_get($payload, 'meta_count', data_get($meta, 'meta_count', data_get($payload, 'meta.meta_count'))),
            'og_count' => data_get($payload, 'og_count', data_get($meta, 'og_count', data_get($payload, 'meta.og_count', data_get($payload, 'og')))),
            'ldjson_count' => data_get($payload, 'ldjson_count', data_get($meta, 'ldjson_count', data_get($payload, 'meta.ldjson_count', data_get($payload, 'ld')))),
            'img_count' => data_get($payload, 'img_count', data_get($meta, 'img_count', data_get($payload, 'meta.img_count', data_get($payload, 'imgs')))),
            'blocking_scripts' => data_get($payload, 'blocking_scripts', data_get($meta, 'blocking_scripts', data_get($payload, 'meta.blocking_scripts', data_get($payload, 'blocking')))),
        ];

        $meta = array_merge($defaults, $meta);

        foreach (['first_byte_ms', 'html_size', 'meta_count', 'og_count', 'ldjson_count', 'img_count', 'blocking_scripts'] as $key) {
            if (array_key_exists($key, $meta) && $meta[$key] !== null) {
                $meta[$key] = (int) $meta[$key];
            }
        }

        $meta['has_json_ld'] = (bool) ($meta['has_json_ld'] ?? (($meta['ldjson_count'] ?? 0) > 0));
        $meta['has_open_graph'] = (bool) ($meta['has_open_graph'] ?? (($meta['og_count'] ?? 0) > 0));

        return $meta;
    }

    /**
     * @param  mixed  $row
     * @return array<string, mixed>
     */
    private function extractMeta($row): array
    {
        $meta = data_get($row, 'meta');

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($meta)) {
            return $meta;
        }

        return [];
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('ssr_metrics');
    }

    /**
     * @return array<int, string>
     */
    private function columns(): array
    {
        if ($this->columnCache === null) {
            $this->columnCache = $this->tableExists()
                ? Schema::getColumnListing('ssr_metrics')
                : [];
        }

        return $this->columnCache;
    }

    private function timestampColumn(): string
    {
        $columns = $this->columns();

        if (in_array('recorded_at', $columns, true)) {
            return 'recorded_at';
        }

        return 'created_at';
    }
}
