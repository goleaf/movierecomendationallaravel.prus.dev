<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CtrAnalyticsService
{
    /**
     * @return array{
     *     summary: array<int, array{variant: string, impressions: int, clicks: int, ctr: float}>,
     *     clicksByPlacement: array<string, int>,
     *     funnels: array<string, array{imps: int, clks: int, views: int}>,
     *     totals: array{impressions: int, clicks: int, views: int},
     *     period: array{from: string, to: string},
     *     variants: array<int, string>,
     *     placements: array<int, string>
     * }
     */
    public function getMetrics(
        string $from,
        string $to,
        ?string $placement = null,
        ?string $variant = null
    ): array {
        $fromDate = CarbonImmutable::parse($from);
        $toDate = CarbonImmutable::parse($to);

        $summaryData = $this->variantSummary($fromDate, $toDate, $placement, $variant);
        $placementCtrData = $this->placementCtrs($fromDate, $toDate);

        $placements = $placementCtrData
            ->map(static fn (array $row) => explode('-', $row['label'])[0])
            ->unique()
            ->values()
            ->all();

        if ($placements === []) {
            $placements = ['home', 'show', 'trends'];
        }

        $clicksByPlacement = array_fill_keys($placements, 0);
        foreach ($summaryData['placementClicks'] as $placementKey => $clickCount) {
            $clicksByPlacement[$placementKey] = (int) $clickCount;
        }

        $funnelsRaw = $this->funnels($fromDate, $toDate, $placements);
        $funnels = [];
        $totalLabel = __('admin.ctr.funnels.total');
        $totalViews = 0;

        foreach ($funnelsRaw as $row) {
            $label = $row['label'];
            $funnels[$label] = [
                'imps' => (int) $row['imps'],
                'clks' => (int) $row['clicks'],
                'views' => (int) $row['views'],
            ];

            if ($label === $totalLabel) {
                $totalViews = (int) $row['views'];
            }
        }

        $totals = [
            'impressions' => array_sum(array_map(
                static fn (array $item): int => (int) $item['impressions'],
                $summaryData['summary']
            )),
            'clicks' => array_sum(array_map(
                static fn (array $item): int => (int) $item['clicks'],
                $summaryData['summary']
            )),
            'views' => $totalViews,
        ];

        return [
            'summary' => $summaryData['summary'],
            'clicksByPlacement' => $clicksByPlacement,
            'funnels' => $funnels,
            'totals' => $totals,
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'variants' => ['A', 'B'],
            'placements' => array_values($placements),
        ];
    }

    /**
     * @return array{summary: array<int, array<string, mixed>>, impressions: array<string, int>, clicks: array<string, int>, placementClicks: array<string, int>}
     */
    public function variantSummary(CarbonImmutable $from, CarbonImmutable $to, ?string $placement = null, ?string $variant = null): array
    {
        if (! Schema::hasTable('rec_ab_logs') || ! Schema::hasTable('rec_clicks')) {
            return [
                'summary' => [],
                'impressions' => [],
                'clicks' => [],
                'placementClicks' => [],
            ];
        }

        [$fromDateTime, $toDateTime] = $this->formatRange($from, $to);

        $logs = DB::table('rec_ab_logs')->whereBetween('created_at', [$fromDateTime, $toDateTime]);
        $clicks = DB::table('rec_clicks')->whereBetween('created_at', [$fromDateTime, $toDateTime]);

        if ($placement !== null && $placement !== '') {
            $logs->where('placement', $placement);
            $clicks->where('placement', $placement);
        }

        if ($variant !== null && $variant !== '') {
            $logs->where('variant', $variant);
            $clicks->where('variant', $variant);
        }

        /** @var array<string, int> $impVariant */
        $impVariant = $logs
            ->select('variant', DB::raw('count(*) as imps'))
            ->groupBy('variant')
            ->pluck('imps', 'variant')
            ->map(fn ($value) => (int) $value)
            ->all();

        /** @var array<string, int> $clkVariant */
        $clkVariant = $clicks
            ->select('variant', DB::raw('count(*) as clks'))
            ->groupBy('variant')
            ->pluck('clks', 'variant')
            ->map(fn ($value) => (int) $value)
            ->all();

        $variants = $variant && $variant !== '' ? [$variant] : ['A', 'B'];
        $summary = [];
        foreach ($variants as $code) {
            $imps = (int) ($impVariant[$code] ?? 0);
            $clks = (int) ($clkVariant[$code] ?? 0);
            $summary[] = [
                'variant' => $code,
                'impressions' => $imps,
                'clicks' => $clks,
                'ctr' => $imps > 0 ? round(100 * $clks / $imps, 2) : 0.0,
            ];
        }

        /** @var array<string, int> $placementClicks */
        $placementClicks = DB::table('rec_clicks')
            ->whereBetween('created_at', [$fromDateTime, $toDateTime])
            ->when($variant !== null && $variant !== '', fn ($query) => $query->where('variant', $variant))
            ->select('placement', DB::raw('count(*) as clks'))
            ->groupBy('placement')
            ->pluck('clks', 'placement')
            ->map(fn ($value) => (int) $value)
            ->all();

        return [
            'summary' => $summary,
            'impressions' => $impVariant,
            'clicks' => $clkVariant,
            'placementClicks' => $placementClicks,
        ];
    }

    /**
     * @return array<int, array{label: string, imps: int, clicks: int, views: int, ctr: float, view_rate: float}>
     */
    public function funnels(CarbonImmutable $from, CarbonImmutable $to, array $placements = ['home', 'show', 'trends']): array
    {
        if (! Schema::hasTable('rec_ab_logs')) {
            return [];
        }

        [$fromDateTime, $toDateTime] = $this->formatRange($from, $to);

        $impressionsQuery = DB::table('rec_ab_logs')
            ->select('placement', DB::raw('count(*) as imps'))
            ->whereBetween('created_at', [$fromDateTime, $toDateTime]);

        if ($placements !== []) {
            $impressionsQuery->whereIn('placement', $placements);
        }

        /** @var array<string, int> $impressions */
        $impressions = $impressionsQuery
            ->groupBy('placement')
            ->pluck('imps', 'placement')
            ->map(fn ($value) => (int) $value)
            ->all();

        /** @var array<string, int> $clicks */
        $clicks = [];
        if (Schema::hasTable('rec_clicks')) {
            $clicksQuery = DB::table('rec_clicks')
                ->select('placement', DB::raw('count(*) as clks'))
                ->whereBetween('created_at', [$fromDateTime, $toDateTime]);

            if ($placements !== []) {
                $clicksQuery->whereIn('placement', $placements);
            }

            $clicks = $clicksQuery
                ->groupBy('placement')
                ->pluck('clks', 'placement')
                ->map(fn ($value) => (int) $value)
                ->all();
        }

        /** @var array<string, int> $views */
        $views = [];
        if (Schema::hasTable('device_history')) {
            $viewsQuery = DB::table('device_history')
                ->select('page', DB::raw('count(*) as views'))
                ->whereBetween('viewed_at', [$fromDateTime, $toDateTime]);

            if ($placements !== []) {
                $viewsQuery->whereIn('page', $placements);
            }

            $views = $viewsQuery
                ->groupBy('page')
                ->pluck('views', 'page')
                ->map(fn ($value) => (int) $value)
                ->all();
        }

        $rows = [];
        $totalImps = 0;
        $totalClicks = 0;
        $totalViews = 0;

        foreach ($placements as $placement) {
            $placementImps = (int) ($impressions[$placement] ?? 0);
            $placementClicks = (int) ($clicks[$placement] ?? 0);
            $placementViews = (int) ($views[$placement] ?? 0);

            $rows[] = [
                'label' => $this->translatePlacement($placement),
                'imps' => $placementImps,
                'clicks' => $placementClicks,
                'views' => $placementViews,
                'ctr' => $placementImps > 0 ? round(100 * $placementClicks / $placementImps, 2) : 0.0,
                'view_rate' => $placementViews > 0 ? round(100 * $placementClicks / $placementViews, 2) : 0.0,
            ];

            $totalImps += $placementImps;
            $totalClicks += $placementClicks;
            $totalViews += $placementViews;
        }

        $rows[] = [
            'label' => __('admin.ctr.funnels.total'),
            'imps' => $totalImps,
            'clicks' => $totalClicks,
            'views' => $totalViews,
            'ctr' => $totalImps > 0 ? round(100 * $totalClicks / $totalImps, 2) : 0.0,
            'view_rate' => $totalViews > 0 ? round(100 * $totalClicks / $totalViews, 2) : 0.0,
        ];

        return $rows;
    }

    /**
     * @return array{days: array<int, string>, series: array<string, array<int, float>>, max: float}
     */
    public function dailySeries(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! Schema::hasTable('rec_ab_logs') || ! Schema::hasTable('rec_clicks')) {
            return [
                'days' => [],
                'series' => ['A' => [], 'B' => []],
                'max' => 0.0,
            ];
        }

        [$fromDateTime, $toDateTime] = $this->formatRange($from, $to);

        $logs = DB::table('rec_ab_logs')
            ->selectRaw('date(created_at) as d, variant, count(*) as imps')
            ->whereBetween('created_at', [$fromDateTime, $toDateTime])
            ->groupBy('d', 'variant')
            ->get();

        $clicks = DB::table('rec_clicks')
            ->selectRaw('date(created_at) as d, variant, count(*) as clks')
            ->whereBetween('created_at', [$fromDateTime, $toDateTime])
            ->groupBy('d', 'variant')
            ->get();

        $days = [];
        $current = $from->startOfDay();
        $end = $to->endOfDay();

        while ($current->lessThanOrEqualTo($end)) {
            $days[] = $current->format('Y-m-d');
            $current = $current->addDay();
        }

        $series = ['A' => [], 'B' => []];
        $max = 0.0;
        foreach ($days as $day) {
            $logA = $logs->first(fn ($row) => $row->d === $day && $row->variant === 'A');
            $logB = $logs->first(fn ($row) => $row->d === $day && $row->variant === 'B');
            $clkA = $clicks->first(fn ($row) => $row->d === $day && $row->variant === 'A');
            $clkB = $clicks->first(fn ($row) => $row->d === $day && $row->variant === 'B');

            $impsA = (int) ($logA->imps ?? 0);
            $impsB = (int) ($logB->imps ?? 0);
            $clksA = (int) ($clkA->clks ?? 0);
            $clksB = (int) ($clkB->clks ?? 0);

            $ctrA = $impsA > 0 ? 100.0 * $clksA / $impsA : 0.0;
            $ctrB = $impsB > 0 ? 100.0 * $clksB / $impsB : 0.0;

            $series['A'][] = round($ctrA, 2);
            $series['B'][] = round($ctrB, 2);
            $max = max($max, $ctrA, $ctrB);
        }

        $max = max(5.0, ceil($max / 5.0) * 5.0);

        return [
            'days' => $days,
            'series' => $series,
            'max' => $max,
        ];
    }

    public function buildDailyCtrSvg(CarbonImmutable $from, CarbonImmutable $to): ?string
    {
        $data = $this->dailySeries($from, $to);

        if ($data['days'] === []) {
            return null;
        }

        $width = 720;
        $height = 260;
        $pad = 40;
        $mapPoints = function (array $values) use ($data, $width, $height, $pad): string {
            $count = count($values);
            if ($count <= 1) {
                $count = 1;
            }

            $points = [];
            foreach ($values as $index => $value) {
                $x = $pad + ($count <= 1 ? 0 : $index * ($width - 2 * $pad) / ($count - 1));
                $y = $height - $pad - ($value / max(1.0, $data['max'])) * ($height - 2 * $pad);
                $points[] = sprintf('%.1f,%.1f', $x, $y);
            }

            return implode(' ', $points);
        };

        $grid = '';
        for ($i = 0; $i <= 5; $i++) {
            $y = $pad + $i * ($height - 2 * $pad) / 5;
            $value = round($data['max'] - $i * $data['max'] / 5, 1);
            $grid .= '<line x1="'.$pad.'" y1="'.($y).'" x2="'.($width - $pad).'" y2="'.($y).'" stroke="#1d2229" stroke-width="1"/>';
            $grid .= '<text x="5" y="'.($y + 4).'" fill="#889" font-size="10">'.$value.'%</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'">'
            .'<rect x="0" y="0" width="'.$width.'" height="'.$height.'" fill="#0b0c0f"/>'
            .$grid
            .'<polyline fill="none" stroke="#5aa0ff" stroke-width="2" points="'.$mapPoints($data['series']['A']).'"/>'
            .'<polyline fill="none" stroke="#8ee38b" stroke-width="2" points="'.$mapPoints($data['series']['B']).'"/>'
            .'<text x="10" y="16" fill="#ddd">CTR по дням: A (синяя) vs B (зелёная)</text>'
            .'</svg>';
    }

    public function buildPlacementCtrSvg(CarbonImmutable $from, CarbonImmutable $to): ?string
    {
        $data = $this->placementCtrs($from, $to);

        if ($data->isEmpty()) {
            return null;
        }

        $width = 720;
        $height = 260;
        $pad = 40;
        $barWidth = 24;
        $gap = 18;
        $max = (float) $data->max('ctr');
        $max = max(5.0, ceil($max / 5.0) * 5.0);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'">'
            .'<rect x="0" y="0" width="'.$width.'" height="'.$height.'" fill="#0b0c0f"/>';

        for ($i = 0; $i <= 5; $i++) {
            $y = $pad + $i * ($height - 2 * $pad) / 5;
            $value = round($max - $i * $max / 5, 1);
            $svg .= '<line x1="'.$pad.'" y1="'.($y).'" x2="'.($width - $pad).'" y2="'.($y).'" stroke="#1d2229" stroke-width="1"/>';
            $svg .= '<text x="5" y="'.($y + 4).'" fill="#889" font-size="10">'.$value.'%</text>';
        }

        $xOffset = $pad + 10;
        $index = 0;
        foreach ($data as $row) {
            $ctr = (float) $row['ctr'];
            $heightValue = ($height - 2 * $pad) * ($ctr / max(1.0, $max));
            $x = $xOffset + $index * ($barWidth + $gap);
            $y = $height - $pad - $heightValue;
            $color = str_contains($row['label'], '-A') ? '#5aa0ff' : '#8ee38b';

            $svg .= '<rect x="'.$x.'" y="'.$y.'" width="'.$barWidth.'" height="'.$heightValue.'" fill="'.$color.'"/>';
            $svg .= '<text x="'.$x.'" y="'.($height - $pad + 12).'" fill="#aaa" font-size="10" transform="rotate(45 '.$x.','.
                ($height - $pad + 12).')">'.$row['label'].'</text>';

            $index++;
        }

        $svg .= '<text x="10" y="16" fill="#ddd">CTR по площадкам (A — синий, B — зелёный)</text>';
        $svg .= '</svg>';

        return $svg;
    }

    /**
     * @return Collection<int, array{label: string, ctr: float}>
     */
    public function placementCtrs(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        if (! Schema::hasTable('rec_clicks') || ! Schema::hasTable('rec_ab_logs')) {
            return collect();
        }

        [$fromDateTime, $toDateTime] = $this->formatRange($from, $to);

        $clicks = DB::table('rec_clicks')
            ->selectRaw('placement, variant, count(*) as clicks')
            ->whereBetween('created_at', [$fromDateTime, $toDateTime])
            ->groupBy('placement', 'variant')
            ->get();

        $impressions = DB::table('rec_ab_logs')
            ->selectRaw('placement, variant, count(*) as impressions')
            ->whereBetween('created_at', [$fromDateTime, $toDateTime])
            ->groupBy('placement', 'variant')
            ->get();

        $placements = $impressions->pluck('placement')
            ->merge($clicks->pluck('placement'))
            ->unique()
            ->values();

        $variants = ['A', 'B'];

        return $placements->flatMap(function (string $placement) use ($variants, $clicks, $impressions) {
            return collect($variants)->map(function (string $variant) use ($placement, $clicks, $impressions) {
                $clickRow = $clicks->firstWhere(fn ($item) => $item->placement === $placement && $item->variant === $variant);
                $impressionRow = $impressions->firstWhere(fn ($item) => $item->placement === $placement && $item->variant === $variant);

                $clickCount = (int) ($clickRow->clicks ?? 0);
                $imps = (int) ($impressionRow->impressions ?? 0);

                return [
                    'label' => $placement.'-'.$variant,
                    'ctr' => $imps > 0 ? round(100 * $clickCount / $imps, 2) : 0.0,
                ];
            });
        })->values();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function formatRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            $from->startOfDay()->format('Y-m-d H:i:s'),
            $to->endOfDay()->format('Y-m-d H:i:s'),
        ];
    }

    private function translatePlacement(string $placement): string
    {
        $key = "admin.ctr.filters.placements.$placement";
        $translated = __($key);

        return $translated === $key ? ucfirst($placement) : $translated;
    }
}
