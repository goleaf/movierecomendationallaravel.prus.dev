<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;

class CtrAnalyticsService
{
    /**
     * @return array{
     *     summary: array<int, array{variant: string, impressions: int, clicks: int, ctr: float}>,
     *     clicksByPlacement: array<string, int>,
     *     funnels: array<string, array{imps: int, clks: int, views: int, ctr: float, cuped_ctr: float, view_rate: float}>,
     *     totals: array{impressions: int, clicks: int, views: int},
     *     period: array{from: string, to: string},
     *     variants: array<int, string>,
     *     placements: array<int, string>
     * }
     */
    public function getMetrics(
        string $from,
        string $to,
        ?string $placement,
        ?string $variant
    ): array {
        $fromDate = CarbonImmutable::parse($from);
        $toDate = CarbonImmutable::parse($to);

        $summaryData = $this->variantSummary($fromDate, $toDate, $placement, $variant);

        /** @var Collection<int, array{label: string, ctr: float}> $placementCtrData */
        $placementCtrData = $this->placementCtrs($fromDate, $toDate);
        $placements = $placementCtrData
            ->map(static function (array $row): string {
                $prefix = strtok($row['label'], '-');

                return is_string($prefix) && $prefix !== '' ? $prefix : $row['label'];
            })
            ->unique()
            ->values()
            ->all();

        if ($placements === []) {
            $placements = ['home', 'show', 'trends'];
        }

        $clicksByPlacement = collect($placements)
            ->mapWithKeys(static fn (string $key): array => [
                $key => (int) ($summaryData['placementClicks'][$key] ?? 0),
            ])
            ->all();

        $funnelRows = $this->funnels($fromDate, $toDate, $placements);
        $funnels = collect($funnelRows)
            ->mapWithKeys(static function (array $row): array {
                return [
                    $row['label'] => [
                        'imps' => (int) $row['imps'],
                        'clks' => (int) $row['clicks'],
                        'views' => (int) $row['views'],
                        'ctr' => (float) $row['ctr'],
                        'cuped_ctr' => (float) $row['cuped_ctr'],
                        'view_rate' => (float) $row['view_rate'],
                    ],
                ];
            })
            ->all();

        $totalLabelTranslation = Lang::get('admin.ctr.funnels.total');
        $totalLabel = is_string($totalLabelTranslation) ? $totalLabelTranslation : 'Total';
        /** @var string $totalLabel */
        $totalViews = $funnels[$totalLabel]['views'] ?? 0;

        /** @var array<int, array{variant: string, impressions: int, clicks: int, ctr: float}> $summary */
        $summary = $summaryData['summary'];
        $totals = [
            'impressions' => array_sum(array_map(
                static fn (array $item): int => (int) $item['impressions'],
                $summary
            )),
            'clicks' => array_sum(array_map(
                static fn (array $item): int => (int) $item['clicks'],
                $summary
            )),
            'views' => (int) $totalViews,
        ];

        $variants = array_values(array_unique(array_merge(
            ['A', 'B'],
            array_map(
                static fn (array $row): string => $row['variant'],
                $summary
            )
        )));

        /** @var list<string> $variants */
        $variants = array_values($variants);

        /** @var list<string> $placements */
        $placements = array_values(array_map(static fn ($value): string => (string) $value, $placements));

        return [
            'summary' => $summary,
            'clicksByPlacement' => $clicksByPlacement,
            'funnels' => $funnels,
            'totals' => $totals,
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'variants' => $variants,
            'placements' => array_values($placements),
        ];
    }

    /**
     * @return array{
     *     summary: array<int, array{variant: string, impressions: int, clicks: int, ctr: float}>,
     *     impressions: array<string, int>,
     *     clicks: array<string, int>,
     *     placementClicks: array<string, int>
     * }
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
            ->map(static fn (int|float|string $value): int => (int) $value)
            ->all();

        /** @var array<string, int> $clkVariant */
        $clkVariant = $clicks
            ->select('variant', DB::raw('count(*) as clks'))
            ->groupBy('variant')
            ->pluck('clks', 'variant')
            ->map(static fn (int|float|string $value): int => (int) $value)
            ->all();

        $variants = $variant && $variant !== '' ? [$variant] : ['A', 'B'];
        /** @var array<int, array{variant: string, impressions: int, clicks: int, ctr: float}> $summary */
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
            ->when($placement !== null && $placement !== '', fn ($query) => $query->where('placement', $placement))
            ->select('placement', DB::raw('count(*) as clks'))
            ->groupBy('placement')
            ->pluck('clks', 'placement')
            ->map(static fn (int|float|string $value): int => (int) $value)
            ->all();

        return [
            'summary' => $summary,
            'impressions' => $impVariant,
            'clicks' => $clkVariant,
            'placementClicks' => $placementClicks,
        ];
    }

    /**
     * @param  array<int, string>  $placements
     * @return array<int, array{label: string, imps: int, clicks: int, views: int, ctr: float, view_rate: float, cuped_ctr: float}>
     */
    public function funnels(CarbonImmutable $from, CarbonImmutable $to, array $placements = ['home', 'show', 'trends']): array
    {
        if (! Schema::hasTable('rec_ab_logs')) {
            return [];
        }

        [$fromDateTime, $toDateTime] = $this->formatRange($from, $to);

        $placements = array_values(array_map(static fn (string $placement): string => $placement, $placements));

        $baselineStats = $this->deviceBaselineStats($from);
        if ($baselineStats['device'] === []) {
            $baselineStats = $this->fetchDeviceBaselineStats(null);
        }

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
            ->map(static fn (int|float|string $value): int => (int) $value)
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
                ->map(static fn (int|float|string $value): int => (int) $value)
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
                ->map(static fn (int|float|string $value): int => (int) $value)
                ->all();
        }

        $deviceImpressionsQuery = DB::table('rec_ab_logs')
            ->select('device_id', 'placement', DB::raw('count(*) as imps'))
            ->whereBetween('created_at', [$fromDateTime, $toDateTime])
            ->groupBy('device_id', 'placement');

        if ($placements !== []) {
            $deviceImpressionsQuery->whereIn('placement', $placements);
        }

        $deviceImpressions = $deviceImpressionsQuery->get();

        $deviceClicks = collect();
        if (Schema::hasTable('rec_clicks')) {
            $deviceClicksQuery = DB::table('rec_clicks')
                ->select('device_id', 'placement', DB::raw('count(*) as clks'))
                ->whereBetween('created_at', [$fromDateTime, $toDateTime])
                ->groupBy('device_id', 'placement');

            if ($placements !== []) {
                $deviceClicksQuery->whereIn('placement', $placements);
            }

            $deviceClicks = $deviceClicksQuery->get();
        }

        /** @var array<string, array<string, int>> $clicksByDevice */
        $clicksByDevice = [];
        foreach ($deviceClicks as $row) {
            $placement = (string) $row->placement;
            $deviceId = (string) $row->device_id;
            $clicksByDevice[$placement][$deviceId] = (int) $row->clks;
        }

        /** @var array<string, array<int, array{device_id: string, imps: int, clicks: int}>> $placementEntries */
        $placementEntries = [];
        /** @var array<int, array{device_id: string, imps: int, clicks: int}> $totalEntries */
        $totalEntries = [];

        foreach ($deviceImpressions as $row) {
            $placement = (string) $row->placement;
            $deviceId = (string) $row->device_id;
            $imps = (int) $row->imps;
            $clickCount = (int) ($clicksByDevice[$placement][$deviceId] ?? 0);

            $entry = [
                'device_id' => $deviceId,
                'imps' => $imps,
                'clicks' => $clickCount,
            ];

            $placementEntries[$placement][] = $entry;
            $totalEntries[] = $entry;
        }

        /** @var array<int, array{label: string, imps: int, clicks: int, views: int, ctr: float, view_rate: float, cuped_ctr: float}> $rows */
        $rows = [];
        $totalImps = 0;
        $totalClicks = 0;
        $totalViews = 0;

        foreach ($placements as $placement) {
            $placementImps = (int) ($impressions[$placement] ?? 0);
            $placementClicks = (int) ($clicks[$placement] ?? 0);
            $placementViews = (int) ($views[$placement] ?? 0);

            $ctr = $placementImps > 0 ? round(100 * $placementClicks / $placementImps, 2) : 0.0;
            $cuped = $this->calculateCupedCtr($placementEntries[$placement] ?? [], $baselineStats);
            $cupedCtr = $cuped ?? $ctr;

            $rows[] = [
                'label' => $this->translatePlacement($placement),
                'imps' => $placementImps,
                'clicks' => $placementClicks,
                'views' => $placementViews,
                'ctr' => $ctr,
                'view_rate' => $placementViews > 0 ? round(100 * $placementClicks / $placementViews, 2) : 0.0,
                'cuped_ctr' => $cupedCtr,
            ];

            $totalImps += $placementImps;
            $totalClicks += $placementClicks;
            $totalViews += $placementViews;
        }

        $totalCtr = $totalImps > 0 ? round(100 * $totalClicks / $totalImps, 2) : 0.0;
        $totalCuped = $this->calculateCupedCtr($totalEntries, $baselineStats) ?? $totalCtr;

        $totalRowLabelTranslation = Lang::get('admin.ctr.funnels.total');
        $totalRowLabel = is_string($totalRowLabelTranslation) ? $totalRowLabelTranslation : 'Total';
        /** @var string $totalRowLabel */
        $rows[] = [
            'label' => $totalRowLabel,
            'imps' => $totalImps,
            'clicks' => $totalClicks,
            'views' => $totalViews,
            'ctr' => $totalCtr,
            'view_rate' => $totalViews > 0 ? round(100 * $totalClicks / $totalViews, 2) : 0.0,
            'cuped_ctr' => $totalCuped,
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

        $max = max(5.0, ceil(max($max, 0.0) / 5.0) * 5.0);

        if (! is_finite($max) || $max <= 0.0) {
            $max = 5.0;
        }

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
        $chartMax = (float) $data['max'];
        if (! is_finite($chartMax) || $chartMax <= 0.0) {
            $chartMax = 5.0;
        }

        $mapPoints = function (array $values) use ($chartMax, $width, $height, $pad): string {
            $values = array_values(array_map(
                static fn ($value): float => is_numeric($value) ? (float) $value : 0.0,
                $values
            ));

            $count = count($values);
            if ($count === 0) {
                return '';
            }

            $horizontalRange = max(1.0, $width - 2 * $pad);
            $verticalRange = max(1.0, $height - 2 * $pad);
            $maxValue = max(1.0, $chartMax);
            $steps = max(1, $count - 1);

            $points = [];
            foreach ($values as $index => $value) {
                $x = $pad + ($count <= 1 ? 0.0 : $index * $horizontalRange / $steps);
                $y = $height - $pad - ($value / $maxValue) * $verticalRange;

                if (! is_finite($x) || ! is_finite($y)) {
                    continue;
                }

                $points[] = sprintf('%.1f,%.1f', $x, $y);
            }

            return implode(' ', $points);
        };

        $grid = '';
        for ($i = 0; $i <= 5; $i++) {
            $y = $pad + $i * ($height - 2 * $pad) / 5;
            $value = round($chartMax - $i * $chartMax / 5, 1);
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
        $max = max(5.0, ceil(max($max, 0.0) / 5.0) * 5.0);

        if (! is_finite($max) || $max <= 0.0) {
            $max = 5.0;
        }

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
        $translated = Lang::get($key);

        if (! is_string($translated) || $translated === $key) {
            return ucfirst($placement);
        }

        /** @var string $translated */
        return $translated;
    }

    /**
     * @return array{device: array<string, float>, mean: float|null}
     */
    private function deviceBaselineStats(?CarbonImmutable $before): array
    {
        $beforeTimestamp = $before?->startOfDay()->format('Y-m-d H:i:s');

        return $this->fetchDeviceBaselineStats($beforeTimestamp);
    }

    /**
     * @return array{device: array<string, float>, mean: float|null}
     */
    private function fetchDeviceBaselineStats(?string $before): array
    {
        if (! Schema::hasTable('rec_ab_logs')) {
            return ['device' => [], 'mean' => null];
        }

        $impressionsQuery = DB::table('rec_ab_logs')
            ->select('device_id', DB::raw('count(*) as imps'));

        if ($before !== null) {
            $impressionsQuery->where('created_at', '<', $before);
        }

        /** @var array<string, int> $impressions */
        $impressions = $impressionsQuery
            ->groupBy('device_id')
            ->pluck('imps', 'device_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        if ($impressions === []) {
            return ['device' => [], 'mean' => null];
        }

        /** @var array<string, int> $clicks */
        $clicks = [];
        if (Schema::hasTable('rec_clicks')) {
            $clicksQuery = DB::table('rec_clicks')
                ->select('device_id', DB::raw('count(*) as clks'));

            if ($before !== null) {
                $clicksQuery->where('created_at', '<', $before);
            }

            $clicks = $clicksQuery
                ->groupBy('device_id')
                ->pluck('clks', 'device_id')
                ->map(fn ($value) => (int) $value)
                ->all();
        }

        $totalImps = 0;
        $totalClicks = 0;
        $baselines = [];

        foreach ($impressions as $deviceId => $imps) {
            if ($imps <= 0) {
                continue;
            }

            $clickCount = (int) ($clicks[$deviceId] ?? 0);
            $baselines[$deviceId] = max(0.0, min(1.0, $clickCount / $imps));

            $totalImps += $imps;
            $totalClicks += $clickCount;
        }

        $mean = $totalImps > 0 ? $totalClicks / $totalImps : null;

        return ['device' => $baselines, 'mean' => $mean];
    }

    /**
     * @param  array<int, array{device_id: string, imps: int, clicks: int}>  $entries
     * @param  array{device: array<string, float>, mean: float|null}  $baselineStats
     */
    private function calculateCupedCtr(array $entries, array $baselineStats): ?float
    {
        if ($entries === []) {
            return null;
        }

        $baselineByDevice = $baselineStats['device'];
        $globalBaseline = $baselineStats['mean'];

        $weightedEntries = [];
        $totalWeight = 0.0;
        $sumX = 0.0;
        $sumY = 0.0;

        foreach ($entries as $entry) {
            $imps = (int) $entry['imps'];
            if ($imps <= 0) {
                continue;
            }

            $deviceId = $entry['device_id'];
            $baseline = $baselineByDevice[$deviceId] ?? $globalBaseline;
            if ($baseline === null) {
                continue;
            }

            $ctr = $entry['clicks'] > 0 ? (float) $entry['clicks'] / $imps : 0.0;

            $weightedEntries[] = [
                'w' => $imps,
                'x' => (float) $baseline,
                'y' => $ctr,
            ];

            $totalWeight += $imps;
            $sumX += $imps * (float) $baseline;
            $sumY += $imps * $ctr;
        }

        if ($weightedEntries === [] || $totalWeight <= 0.0) {
            return null;
        }

        $meanX = $sumX / $totalWeight;
        $meanY = $sumY / $totalWeight;

        $varianceX = 0.0;
        $covariance = 0.0;

        foreach ($weightedEntries as $entry) {
            $dx = $entry['x'] - $meanX;
            $dy = $entry['y'] - $meanY;
            $varianceX += $entry['w'] * $dx * $dx;
            $covariance += $entry['w'] * $dx * $dy;
        }

        $theta = $varianceX > 0.0 ? $covariance / $varianceX : 0.0;

        $adjustedSum = 0.0;
        foreach ($weightedEntries as $entry) {
            $adjusted = $entry['y'] - $theta * ($entry['x'] - $meanX);
            $adjustedSum += $entry['w'] * $adjusted;
        }

        $adjustedMean = $adjustedSum / $totalWeight;

        return round($adjustedMean * 100, 2);
    }
}
