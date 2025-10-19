<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CtrAnalyticsService
{
    /**
     * @return array{
     *     period: array{from: string, to: string},
     *     variants: array<int, string>,
     *     placements: array<int, string>,
     *     summary: array<int, array{variant: string, impressions: int, clicks: int, ctr: float}>,
     *     clicksByPlacement: array<string, int>,
     *     funnels: array<string, array{imps: int, clks: int, views: int}>,
     *     totals: array{impressions: int, clicks: int, views: int}
     * }
     */
    public function getMetrics(string $fromDate, string $toDate, ?string $placement, ?string $variant): array
    {
        $from = CarbonImmutable::parse($fromDate)->startOfDay();
        $to = CarbonImmutable::parse($toDate)->endOfDay();

        $logsQuery = DB::table('rec_ab_logs')->whereBetween('created_at', [$from, $to]);
        $clicksQuery = DB::table('rec_clicks')->whereBetween('created_at', [$from, $to]);

        if ($placement !== null && $placement !== '') {
            $clicksQuery->where('placement', $placement);
        }

        if ($variant !== null && $variant !== '') {
            $logsQuery->where('variant', $variant);
            $clicksQuery->where('variant', $variant);
        }

        $impressionsByVariant = $logsQuery
            ->select('variant', DB::raw('count(*) as imps'))
            ->groupBy('variant')
            ->pluck('imps', 'variant')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $clicksByVariant = $clicksQuery
            ->select('variant', DB::raw('count(*) as clks'))
            ->groupBy('variant')
            ->pluck('clks', 'variant')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $variants = collect(array_unique(array_merge(
            array_keys($impressionsByVariant),
            array_keys($clicksByVariant),
        )))
            ->filter(static fn ($value): bool => $value !== null && $value !== '')
            ->values()
            ->all();

        if ($variants === []) {
            $variants = ['A', 'B'];
        }

        $summary = collect($variants)
            ->map(function (string $currentVariant) use ($impressionsByVariant, $clicksByVariant): array {
                $impressions = (int) Arr::get($impressionsByVariant, $currentVariant, 0);
                $clicks = (int) Arr::get($clicksByVariant, $currentVariant, 0);
                $ctr = $impressions > 0 ? round(100 * $clicks / $impressions, 2) : 0.0;

                return [
                    'variant' => $currentVariant,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'ctr' => $ctr,
                ];
            })
            ->all();

        $clicksByPlacement = DB::table('rec_clicks')
            ->whereBetween('created_at', [$from, $to])
            ->when($variant !== null && $variant !== '', static function ($query) use ($variant): void {
                $query->where('variant', $variant);
            })
            ->select('placement', DB::raw('count(*) as clks'))
            ->groupBy('placement')
            ->orderBy('placement')
            ->pluck('clks', 'placement')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $totalImpressions = array_sum($impressionsByVariant);
        $totalClicks = array_sum($clicksByVariant);
        $totalViews = (int) DB::table('device_history')
            ->whereBetween('viewed_at', [$from, $to])
            ->count();

        $placements = DB::table('rec_clicks')
            ->distinct()
            ->orderBy('placement')
            ->pluck('placement')
            ->filter(static fn ($value): bool => $value !== null && $value !== '')
            ->values()
            ->all();

        $funnels = collect(['home', 'show', 'trends'])
            ->mapWithKeys(function (string $key) use ($from, $to, $totalImpressions, $totalViews): array {
                $clicks = (int) DB::table('rec_clicks')
                    ->whereBetween('created_at', [$from, $to])
                    ->where('placement', $key)
                    ->count();

                return [
                    $key => [
                        'imps' => $totalImpressions,
                        'clks' => $clicks,
                        'views' => $totalViews,
                    ],
                ];
            })
            ->all();

        $funnels['total'] = [
            'imps' => $totalImpressions,
            'clks' => (int) DB::table('rec_clicks')
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'views' => $totalViews,
        ];

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'variants' => $variants,
            'placements' => $placements,
            'summary' => $summary,
            'clicksByPlacement' => $clicksByPlacement,
            'funnels' => $funnels,
            'totals' => [
                'impressions' => $totalImpressions,
                'clicks' => $totalClicks,
                'views' => $totalViews,
            ],
        ];
    }
}
