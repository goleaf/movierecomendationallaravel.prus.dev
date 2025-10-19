<?php

declare(strict_types=1);

namespace App\Reports;

use App\Queries\Ctr\CtrDailyMetricsQuery;
use App\Queries\Ctr\CtrFunnelsQuery;
use App\Queries\Ctr\CtrPlacementBreakdownQuery;
use App\Queries\Ctr\CtrVariantSummaryQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CtrReport
{
    public function __construct(
        private readonly CtrVariantSummaryQuery $variantSummaryQuery,
        private readonly CtrPlacementBreakdownQuery $placementBreakdownQuery,
        private readonly CtrFunnelsQuery $funnelsQuery,
        private readonly CtrDailyMetricsQuery $dailyMetricsQuery,
    ) {}

    public function variantSummary(CarbonImmutable $from, CarbonImmutable $to, ?string $placement, ?string $variant): array
    {
        $variantRows = $this->variantSummaryQuery
            ->build($from, $to, $placement, $variant)
            ->get();

        $impressions = [];
        $clicks = [];
        foreach ($variantRows as $row) {
            $code = (string) $row->variant;
            $impressions[$code] = (int) $row->impressions;
            $clicks[$code] = (int) $row->clicks;
        }

        $variants = $variant !== null && $variant !== '' ? [$variant] : ['A', 'B'];

        $summary = [];
        foreach ($variants as $code) {
            $imps = (int) ($impressions[$code] ?? 0);
            $clks = (int) ($clicks[$code] ?? 0);

            $summary[] = [
                'variant' => (string) $code,
                'impressions' => $imps,
                'clicks' => $clks,
                'ctr' => $imps > 0 ? (float) round(100 * $clks / $imps, 2) : 0.0,
            ];
        }

        $placementRows = $this->placementBreakdownQuery
            ->build($from, $to, $placement !== null && $placement !== '' ? [$placement] : [], $variant)
            ->get();

        $placementClicks = [];
        foreach ($placementRows as $row) {
            $placementKey = (string) $row->placement;
            $placementClicks[$placementKey] = (int) ($placementClicks[$placementKey] ?? 0) + (int) $row->clicks;
        }

        return [
            'summary' => $summary,
            'impressions' => $impressions,
            'clicks' => $clicks,
            'placementClicks' => $placementClicks,
        ];
    }

    /**
     * @param  list<string>  $placements
     * @return list<array{placement: string, impressions: int, clicks: int, views: int}>
     */
    public function funnels(CarbonImmutable $from, CarbonImmutable $to, array $placements = []): array
    {
        $rows = $this->funnelsQuery
            ->build($from, $to, $placements)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'placement' => (string) $row->placement,
                'impressions' => (int) $row->impressions,
                'clicks' => (int) $row->clicks,
                'views' => (int) $row->views,
            ];
        }

        return $result;
    }

    /**
     * @param  list<string>  $placements
     * @return Collection<int, array{placement: string, variant: string, impressions: int, clicks: int}>
     */
    public function placementBreakdown(CarbonImmutable $from, CarbonImmutable $to, array $placements = [], ?string $variant = null): Collection
    {
        $rows = $this->placementBreakdownQuery
            ->build($from, $to, $placements, $variant)
            ->get();

        return collect($rows)->map(static fn ($row): array => [
            'placement' => (string) $row->placement,
            'variant' => (string) $row->variant,
            'impressions' => (int) $row->impressions,
            'clicks' => (int) $row->clicks,
        ]);
    }

    /**
     * @return array{impressions: array<string, array<string, int>>, clicks: array<string, array<string, int>>}
     */
    public function dailyMetrics(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->dailyMetricsQuery
            ->build($from, $to)
            ->get();

        $impressions = [];
        $clicks = [];

        foreach ($rows as $row) {
            $day = (string) $row->day;
            $variant = (string) $row->variant;
            $impressions[$day][$variant] = (int) $row->impressions;
            $clicks[$day][$variant] = (int) $row->clicks;
        }

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
        ];
    }
}
