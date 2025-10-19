<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\SsrMetric;
use App\Support\SsrMetricPayload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\FilesystemAdapter;
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
            $timestampColumn = $this->metricTimestampColumn();
            $row = DB::table('ssr_metrics')
                ->orderByDesc($timestampColumn)
                ->orderByDesc('id')
                ->first();

            if ($row !== null) {
                $score = (int) ($row->score ?? 0);
                $paths = 1;
            }
        } else {
            $records = $this->fallbackNormalizedRecords();
            $paths = count($records);

            if ($paths > 0) {
                $total = array_sum(array_map(static fn (array $record): int => $record['score'], $records));
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
            $timestampColumn = $this->metricTimestampColumn();
            $rows = DB::table('ssr_metrics')
                ->selectRaw(sprintf('date(%s) as d, avg(score) as s', $timestampColumn))
                ->groupBy('d')
                ->orderBy('d')
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $labels[] = $row->d;
                $series[] = round((float) $row->s, 2);
            }
        } else {
            $records = $this->fallbackNormalizedRecords();

            if ($records !== []) {
                $grouped = [];

                foreach ($records as $record) {
                    $date = $record['recorded_at']->toDateString();
                    $grouped[$date] ??= [];
                    $grouped[$date][] = $record['score'];
                }

                ksort($grouped);
                $grouped = array_slice($grouped, -$limit, null, true);

                foreach ($grouped as $date => $scores) {
                    $labels[] = $date;
                    $series[] = round(array_sum($scores) / count($scores), 2);
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

    /**
     * @return array<int, array{path: string, score: int, recorded_at: CarbonImmutable, raw: array<string, mixed>, normalized: array<string, mixed>}>|array{}
     */
    public function recent(int $limit = 15): array
    {
        if (Schema::hasTable('ssr_metrics')) {
            $timestampColumn = $this->metricTimestampColumn();

            return SsrMetric::query()
                ->orderByDesc($timestampColumn)
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (SsrMetric $metric): array => $this->mapMetricRecord($metric, $timestampColumn))
                ->all();
        }

        $records = array_reverse($this->fallbackNormalizedRecords());

        return array_slice($records, 0, $limit);
    }

    public function dropQuery(): Builder|Relation|null
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return null;
        }

        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();
        $timestampColumn = $this->metricTimestampColumn();

        return SsrMetric::query()
            ->fromSub(function ($query) use ($today, $yesterday, $timestampColumn): void {
                $query
                    ->fromSub(function ($aggregateQuery) use ($today, $yesterday, $timestampColumn): void {
                        $aggregateQuery
                            ->from('ssr_metrics')
                            ->selectRaw(sprintf('path, date(%s) as d, avg(score) as avg_score', $timestampColumn))
                            ->whereIn(DB::raw(sprintf('date(%s)', $timestampColumn)), [$yesterday, $today])
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

    private function metricTimestampColumn(): string
    {
        return Schema::hasColumn('ssr_metrics', 'recorded_at') ? 'recorded_at' : 'created_at';
    }

    private function payloadColumn(): ?string
    {
        return $this->resolveColumn(['payload', 'raw_payload']);
    }

    private function normalizedPayloadColumn(): ?string
    {
        return $this->resolveColumn(['normalized_payload', 'payload_normalized']);
    }

    private function resolveColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn('ssr_metrics', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{path: string, score: int, recorded_at: CarbonImmutable, raw: array<string, mixed>, normalized: array<string, mixed>}
     */
    private function mapMetricRecord(SsrMetric $metric, string $timestampColumn): array
    {
        $recordedAt = $metric->{$timestampColumn} ?? $metric->created_at;
        $recordedAt = $recordedAt instanceof CarbonImmutable
            ? $recordedAt
            : CarbonImmutable::parse((string) $recordedAt);

        $raw = $this->extractRawPayload($metric);
        $normalized = $this->extractNormalizedPayload($metric, $raw);

        return [
            'path' => $normalized['path'],
            'score' => $normalized['score'],
            'recorded_at' => $recordedAt,
            'raw' => $raw,
            'normalized' => $normalized,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractRawPayload(SsrMetric $metric): array
    {
        $raw = [];
        $payloadColumn = $this->payloadColumn();

        if ($payloadColumn !== null) {
            $value = $metric->{$payloadColumn};

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                }
            } elseif (is_array($value)) {
                $raw = $value;
            }
        }

        if ($raw === []) {
            $raw = [
                'path' => $metric->path,
                'score' => (int) ($metric->score ?? 0),
                'html_size' => (int) ($metric->size ?? 0),
                'meta_count' => (int) ($metric->meta_count ?? 0),
                'og_count' => (int) ($metric->og_count ?? 0),
                'ldjson_count' => (int) ($metric->ldjson_count ?? 0),
                'img_count' => (int) ($metric->img_count ?? 0),
                'blocking_scripts' => (int) ($metric->blocking_scripts ?? 0),
                'first_byte_ms' => (int) ($metric->first_byte_ms ?? 0),
            ];

            if (! empty($metric->meta)) {
                $decodedMeta = json_decode((string) $metric->meta, true);
                if (is_array($decodedMeta)) {
                    $raw['meta'] = $decodedMeta;
                }
            }
        }

        $raw['path'] = $raw['path'] ?? $metric->path;
        $raw['score'] = (int) ($raw['score'] ?? $metric->score ?? 0);
        $raw['first_byte_ms'] = (int) ($raw['first_byte_ms'] ?? $metric->first_byte_ms ?? 0);

        return $raw;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function extractNormalizedPayload(SsrMetric $metric, array $raw): array
    {
        $normalizedColumn = $this->normalizedPayloadColumn();

        if ($normalizedColumn !== null) {
            $value = $metric->{$normalizedColumn};

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return SsrMetricPayload::normalize(array_merge($raw, $decoded));
                }
            } elseif (is_array($value)) {
                return SsrMetricPayload::normalize(array_merge($raw, $value));
            }
        }

        $legacy = array_merge($raw, [
            'meta_count' => $raw['meta_count'] ?? $metric->meta_count,
            'og_count' => $raw['og_count'] ?? $metric->og_count,
            'ldjson_count' => $raw['ldjson_count'] ?? $metric->ldjson_count,
            'img_count' => $raw['img_count'] ?? $metric->img_count,
            'blocking_scripts' => $raw['blocking_scripts'] ?? $metric->blocking_scripts,
            'html_size' => $raw['html_size'] ?? $metric->size,
            'first_byte_ms' => $raw['first_byte_ms'] ?? $metric->first_byte_ms,
        ]);

        return SsrMetricPayload::normalize($legacy);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackRawRecords(): array
    {
        $records = [];
        $disk = $this->storage();

        if ($disk->exists('metrics/ssr.jsonl')) {
            $content = trim((string) $disk->get('metrics/ssr.jsonl'));

            if ($content !== '') {
                $lines = preg_split('/\r?\n/', $content) ?: [];

                foreach ($lines as $line) {
                    if ($line === '') {
                        continue;
                    }

                    $decoded = json_decode($line, true);
                    if (is_array($decoded)) {
                        $records[] = $this->upgradeFallbackRecord($decoded);
                    }
                }
            }
        }

        if ($records === [] && $disk->exists('metrics/last.json')) {
            $decoded = json_decode((string) $disk->get('metrics/last.json'), true);
            if (is_array($decoded)) {
                $items = array_is_list($decoded) ? $decoded : [$decoded];
                foreach ($items as $item) {
                    if (is_array($item)) {
                        $records[] = $this->upgradeFallbackRecord($item);
                    }
                }
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function upgradeFallbackRecord(array $record): array
    {
        if (! isset($record['recorded_at']) && isset($record['ts'])) {
            $record['recorded_at'] = $record['ts'];
        }

        if (! isset($record['html_size']) && isset($record['size'])) {
            $record['html_size'] = $record['size'];
        }

        if (! isset($record['og_count']) && isset($record['og'])) {
            $record['og_count'] = $record['og'];
        }

        if (! isset($record['ldjson_count']) && isset($record['ld'])) {
            $record['ldjson_count'] = $record['ld'];
        }

        if (! isset($record['img_count']) && isset($record['imgs'])) {
            $record['img_count'] = $record['imgs'];
        }

        if (! isset($record['blocking_scripts']) && isset($record['blocking'])) {
            $record['blocking_scripts'] = $record['blocking'];
        }

        if (! isset($record['score']) && isset($record['raw']['score'])) {
            $record['score'] = $record['raw']['score'];
        }

        if (! isset($record['path']) && isset($record['raw']['path'])) {
            $record['path'] = $record['raw']['path'];
        }

        if (! isset($record['first_byte_ms']) && isset($record['raw']['first_byte_ms'])) {
            $record['first_byte_ms'] = $record['raw']['first_byte_ms'];
        }

        return $record;
    }

    /**
     * @return array<int, array{path: string, score: int, recorded_at: CarbonImmutable, raw: array<string, mixed>, normalized: array<string, mixed>}>|array{}
     */
    private function fallbackNormalizedRecords(): array
    {
        return array_map(function (array $record): array {
            $recordedAtString = $record['recorded_at'] ?? null;
            $recordedAt = $recordedAtString !== null
                ? CarbonImmutable::parse((string) $recordedAtString)
                : CarbonImmutable::now();

            $raw = $record['raw'] ?? [];
            if (! is_array($raw)) {
                $raw = [];
            }

            $normalized = SsrMetricPayload::normalize(array_merge($record, $raw));

            if ($raw === []) {
                $raw = [
                    'path' => $normalized['path'],
                    'score' => $normalized['score'],
                    'first_byte_ms' => $normalized['first_byte_ms'],
                    'html_size' => $normalized['html_bytes'],
                ];
            }

            return [
                'path' => $normalized['path'],
                'score' => $normalized['score'],
                'recorded_at' => $recordedAt,
                'raw' => $raw,
                'normalized' => $normalized,
            ];
        }, $this->fallbackRawRecords());
    }

    private function storage(): FilesystemAdapter
    {
        return Storage::disk(config('filesystems.default', 'local'));
    }
}
