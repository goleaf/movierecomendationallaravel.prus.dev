<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Analytics\CtrAnalyticsService;
use App\Support\MetricsCache;
use App\Support\SsrMetricPayload;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PrometheusMetricsService
{
    private const CACHE_TAG = 'metrics:prometheus';

    private const CACHE_KEY = 'snapshot';

    private const CACHE_TTL_SECONDS = 60;

    public function __construct(
        private readonly MetricsCache $cache,
        private readonly CtrAnalyticsService $ctrAnalytics,
    ) {}

    public function render(): string
    {
        $metrics = $this->cache->remember(
            self::CACHE_TAG,
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            function (): array {
                return $this->collectMetrics();
            },
        );

        return $this->formatMetrics($metrics);
    }

    /**
     * @return array<int, array{name:string,type:string,help:string,value:int|float}>
     */
    private function collectMetrics(): array
    {
        $metrics = [];

        $metrics = array_merge($metrics, $this->ctrMetrics());
        $metrics = array_merge($metrics, $this->ssrMetrics());
        $metrics = array_merge($metrics, $this->importerMetrics());

        return $metrics;
    }

    /**
     * @return array<int, array{name:string,type:string,help:string,value:int|float}>
     */
    private function ctrMetrics(): array
    {
        $from = CarbonImmutable::now()->subDay();
        $to = CarbonImmutable::now();

        $summary = $this->ctrAnalytics->variantSummary($from, $to);
        $impressions = array_sum(array_map('intval', $summary['impressions'] ?? []));
        $clicks = array_sum(array_map('intval', $summary['clicks'] ?? []));
        $ctr = $impressions > 0 ? $clicks / $impressions : 0.0;

        return [
            $this->metric(
                'movierec_ctr_impressions_total',
                'counter',
                'Total recommendation impressions observed during the last 24 hours.',
                $impressions,
            ),
            $this->metric(
                'movierec_ctr_clicks_total',
                'counter',
                'Total recommendation clicks observed during the last 24 hours.',
                $clicks,
            ),
            $this->metric(
                'movierec_ctr_rate',
                'gauge',
                'Recommendation click-through rate observed during the last 24 hours.',
                $ctr,
            ),
        ];
    }

    /**
     * @return array<int, array{name:string,type:string,help:string,value:int|float}>
     */
    private function ssrMetrics(): array
    {
        $from = CarbonImmutable::now()->subDay();
        $avgScore = 0.0;
        $avgFirstByte = 0.0;
        $samples = 0;

        if (Schema::hasTable('ssr_metrics')) {
            $row = DB::table('ssr_metrics')
                ->selectRaw('avg(score) as avg_score, avg(first_byte_ms) as avg_first_byte, count(*) as sample_size')
                ->where('created_at', '>=', $from->toDateTimeString())
                ->first();

            if ($row !== null) {
                $avgScore = (float) ($row->avg_score ?? 0.0);
                $avgFirstByte = (float) ($row->avg_first_byte ?? 0.0);
                $samples = (int) ($row->sample_size ?? 0);
            }
        }

        if ($samples === 0) {
            $fallback = $this->loadSsrFallback();
            if ($fallback !== []) {
                $samples = count($fallback);
                $avgScore = $this->averageFromRecords($fallback, 'score');
                $avgFirstByte = $this->averageFromRecords($fallback, 'first_byte_ms');
            }
        }

        return [
            $this->metric(
                'movierec_ssr_score_average',
                'gauge',
                'Average SSR score collected during the last 24 hours.',
                $avgScore,
            ),
            $this->metric(
                'movierec_ssr_first_byte_ms_average',
                'gauge',
                'Average SSR first byte time in milliseconds collected during the last 24 hours.',
                $avgFirstByte,
            ),
            $this->metric(
                'movierec_ssr_samples_total',
                'counter',
                'SSR metric samples collected during the last 24 hours.',
                $samples,
            ),
        ];
    }

    /**
     * @return array<int, array{name:string,type:string,help:string,value:int|float}>
     */
    private function importerMetrics(): array
    {
        $from = CarbonImmutable::now()->subDay();
        $failures = 0;

        if (Schema::hasTable('failed_jobs')) {
            $failures = (int) DB::table('failed_jobs')
                ->where('queue', 'importers')
                ->where('failed_at', '>=', $from->toDateTimeString())
                ->count();
        }

        return [
            $this->metric(
                'movierec_importer_failures_total',
                'counter',
                'Importer job failures recorded during the last 24 hours.',
                $failures,
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    private function averageFromRecords(array $records, string $key): float
    {
        $total = 0.0;
        $count = 0;

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $value = $record[$key] ?? null;
            if ($value === null || ! is_numeric($value)) {
                continue;
            }

            $total += (float) $value;
            $count++;
        }

        if ($count === 0) {
            return 0.0;
        }

        return $total / $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadSsrFallback(): array
    {
        $records = [];
        $disk = $this->storage();

        $upgrade = static function (array $record): array {
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

            return $record;
        };

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
                        $records[] = $upgrade($decoded);
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
                        $records[] = $upgrade($item);
                    }
                }
            }
        }

        return array_map(static function (array $record): array {
            $raw = $record['raw'] ?? [];
            if (! is_array($raw)) {
                $raw = [];
            }

            $normalized = SsrMetricPayload::normalize(array_merge($record, $raw));

            return [
                'score' => $normalized['score'],
                'first_byte_ms' => $normalized['first_byte_ms'],
            ];
        }, $records);
    }

    private function storage(): FilesystemAdapter
    {
        return Storage::disk(config('filesystems.default', 'local'));
    }

    private function metric(string $name, string $type, string $help, int|float $value): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'help' => $help,
            'value' => $value,
        ];
    }

    /**
     * @param  array<int, array{name:string,type:string,help:string,value:int|float}>  $metrics
     */
    private function formatMetrics(array $metrics): string
    {
        $lines = [];

        foreach ($metrics as $metric) {
            $lines[] = sprintf('# HELP %s %s', $metric['name'], $metric['help']);
            $lines[] = sprintf('# TYPE %s %s', $metric['name'], $metric['type']);
            $lines[] = sprintf('%s %s', $metric['name'], $this->formatValue($metric['value']));
        }

        return implode("\n", $lines)."\n";
    }

    private function formatValue(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        $formatted = number_format($value, 4, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
