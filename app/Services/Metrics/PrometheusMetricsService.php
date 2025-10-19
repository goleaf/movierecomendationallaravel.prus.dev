<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Services\Analytics\CtrAnalyticsService;
use App\Support\MetricsCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function collect;

class PrometheusMetricsService
{
    private const CTR_LOOKBACK_DAYS = 7;

    public function __construct(
        private readonly CtrAnalyticsService $ctrAnalytics,
        private readonly MetricsCache $cache
    ) {}

    public function render(): string
    {
        /** @var array{ctr: array{variants: array<string, array{impressions: int, clicks: int, ctr: float}>, total: array{impressions: int, clicks: int, ctr: float}}, ssr: array{count: int, average: float, min: float, max: float}, importers: array{failures: int}} $snapshot */
        $snapshot = $this->cache->remember('snapshot', fn (): array => $this->gather());

        return $this->format($snapshot);
    }

    /**
     * @return array{
     *     ctr: array{
     *         variants: array<string, array{impressions: int, clicks: int, ctr: float}>,
     *         total: array{impressions: int, clicks: int, ctr: float},
     *     },
     *     ssr: array{count: int, average: float, min: float, max: float},
     *     importers: array{failures: int},
     * }
     */
    private function gather(): array
    {
        return [
            'ctr' => $this->gatherCtrMetrics(),
            'ssr' => $this->gatherSsrMetrics(),
            'importers' => $this->gatherImporterFailures(),
        ];
    }

    /**
     * @return array{
     *     variants: array<string, array{impressions: int, clicks: int, ctr: float}>,
     *     total: array{impressions: int, clicks: int, ctr: float},
     * }
     */
    private function gatherCtrMetrics(): array
    {
        if (! Schema::hasTable('rec_ab_logs') || ! Schema::hasTable('rec_clicks')) {
            return [
                'variants' => [
                    'A' => ['impressions' => 0, 'clicks' => 0, 'ctr' => 0.0],
                    'B' => ['impressions' => 0, 'clicks' => 0, 'ctr' => 0.0],
                ],
                'total' => ['impressions' => 0, 'clicks' => 0, 'ctr' => 0.0],
            ];
        }

        $to = CarbonImmutable::now();
        $from = $to->subDays(self::CTR_LOOKBACK_DAYS)->startOfDay();

        $summary = $this->ctrAnalytics->variantSummary($from, $to, null, null);

        /** @var array<int, array{variant: string, impressions: int, clicks: int, ctr: float}> $summaryRows */
        $summaryRows = $summary['summary'] ?? [];

        $variants = collect($summaryRows)
            ->pluck('variant')
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach (['A', 'B'] as $baseline) {
            if (! in_array($baseline, $variants, true)) {
                $variants[] = $baseline;
            }
        }

        sort($variants);

        $result = [];
        $totalImpressions = 0;
        $totalClicks = 0;

        foreach ($variants as $variant) {
            $impressions = (int) ($summary['impressions'][$variant] ?? 0);
            $clicks = (int) ($summary['clicks'][$variant] ?? 0);
            $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;

            $result[$variant] = [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $ctr,
            ];

            $totalImpressions += $impressions;
            $totalClicks += $clicks;
        }

        $totalCtr = $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0.0;

        return [
            'variants' => $result,
            'total' => [
                'impressions' => $totalImpressions,
                'clicks' => $totalClicks,
                'ctr' => $totalCtr,
            ],
        ];
    }

    /**
     * @return array{count: int, average: float, min: float, max: float}
     */
    private function gatherSsrMetrics(): array
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return ['count' => 0, 'average' => 0.0, 'min' => 0.0, 'max' => 0.0];
        }

        $values = collect();

        if (Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
            $values = DB::table('ssr_metrics')
                ->whereNotNull('first_byte_ms')
                ->pluck('first_byte_ms')
                ->map(static fn ($value): float => (float) $value);
        }

        if ($values->isEmpty() && Schema::hasColumn('ssr_metrics', 'meta')) {
            $metaValues = DB::table('ssr_metrics')
                ->whereNotNull('meta')
                ->pluck('meta')
                ->map(static function ($meta): ?float {
                    if (! is_string($meta) || $meta === '') {
                        return null;
                    }

                    $decoded = json_decode($meta, true);

                    if (! is_array($decoded)) {
                        return null;
                    }

                    $value = $decoded['first_byte_ms'] ?? null;

                    if ($value === null) {
                        return null;
                    }

                    return (float) $value;
                })
                ->filter()
                ->values();

            $values = $metaValues;
        }

        $count = $values->count();

        if ($count === 0) {
            return ['count' => 0, 'average' => 0.0, 'min' => 0.0, 'max' => 0.0];
        }

        $average = round((float) $values->avg(), 2);
        $min = (float) $values->min();
        $max = (float) $values->max();

        return [
            'count' => $count,
            'average' => $average,
            'min' => $min,
            'max' => $max,
        ];
    }

    /**
     * @return array{failures: int}
     */
    private function gatherImporterFailures(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['failures' => 0];
        }

        $query = DB::table('failed_jobs');

        if (Schema::hasColumn('failed_jobs', 'queue')) {
            $query->where('queue', 'importers');
        }

        return ['failures' => (int) $query->count()];
    }

    /**
     * @param  array{
     *     ctr: array{
     *         variants: array<string, array{impressions: int, clicks: int, ctr: float}>,
     *         total: array{impressions: int, clicks: int, ctr: float},
     *     },
     *     ssr: array{count: int, average: float, min: float, max: float},
     *     importers: array{failures: int},
     * }  $snapshot
     */
    private function format(array $snapshot): string
    {
        $lines = [];

        $lines[] = '# HELP app_ctr_impressions_total Total CTR impressions recorded per variant in the last seven days.';
        $lines[] = '# TYPE app_ctr_impressions_total counter';
        foreach ($snapshot['ctr']['variants'] as $variant => $metrics) {
            $lines[] = sprintf('app_ctr_impressions_total{variant="%s"} %d', $variant, $metrics['impressions']);
        }
        $lines[] = sprintf('app_ctr_impressions_total{variant="total"} %d', $snapshot['ctr']['total']['impressions']);

        $lines[] = '# HELP app_ctr_clicks_total Total CTR clicks recorded per variant in the last seven days.';
        $lines[] = '# TYPE app_ctr_clicks_total counter';
        foreach ($snapshot['ctr']['variants'] as $variant => $metrics) {
            $lines[] = sprintf('app_ctr_clicks_total{variant="%s"} %d', $variant, $metrics['clicks']);
        }
        $lines[] = sprintf('app_ctr_clicks_total{variant="total"} %d', $snapshot['ctr']['total']['clicks']);

        $lines[] = '# HELP app_ctr_ctr_percentage CTR percentage per variant in the last seven days.';
        $lines[] = '# TYPE app_ctr_ctr_percentage gauge';
        foreach ($snapshot['ctr']['variants'] as $variant => $metrics) {
            $lines[] = sprintf('app_ctr_ctr_percentage{variant="%s"} %.2f', $variant, $metrics['ctr']);
        }
        $lines[] = sprintf('app_ctr_ctr_percentage{variant="total"} %.2f', $snapshot['ctr']['total']['ctr']);

        $lines[] = '# HELP app_ssr_ttfb_average_milliseconds Average SSR time to first byte in milliseconds.';
        $lines[] = '# TYPE app_ssr_ttfb_average_milliseconds gauge';
        $lines[] = sprintf('app_ssr_ttfb_average_milliseconds %.2f', $snapshot['ssr']['average']);

        $lines[] = '# HELP app_ssr_ttfb_min_milliseconds Minimum SSR time to first byte in milliseconds.';
        $lines[] = '# TYPE app_ssr_ttfb_min_milliseconds gauge';
        $lines[] = sprintf('app_ssr_ttfb_min_milliseconds %.2f', $snapshot['ssr']['min']);

        $lines[] = '# HELP app_ssr_ttfb_max_milliseconds Maximum SSR time to first byte in milliseconds.';
        $lines[] = '# TYPE app_ssr_ttfb_max_milliseconds gauge';
        $lines[] = sprintf('app_ssr_ttfb_max_milliseconds %.2f', $snapshot['ssr']['max']);

        $lines[] = '# HELP app_ssr_ttfb_samples_total Number of SSR samples considered for TTFB metrics.';
        $lines[] = '# TYPE app_ssr_ttfb_samples_total counter';
        $lines[] = sprintf('app_ssr_ttfb_samples_total %d', $snapshot['ssr']['count']);

        $lines[] = '# HELP app_importer_failed_jobs_total Total importer job failures recorded.';
        $lines[] = '# TYPE app_importer_failed_jobs_total gauge';
        $lines[] = sprintf('app_importer_failed_jobs_total %d', $snapshot['importers']['failures']);

        return implode("\n", $lines)."\n";
    }
}
