<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\SsrMetricsAggregator;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SsrStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $summary = app(SsrMetricsAggregator::class)->summary();
        $periods = $summary['periods'] ?? [];

        if ($periods === []) {
            $label = $summary['label'] ?? __('analytics.widgets.ssr_stats.label');
            $description = $summary['description'] ?? __('analytics.widgets.ssr_stats.empty');

            return [
                Stat::make($label, '—')
                    ->description($description)
                    ->color('gray'),
            ];
        }

        $stats = [];

        foreach (['today', 'yesterday', 'seven_days'] as $key) {
            if (! isset($periods[$key])) {
                continue;
            }

            $stats[] = $this->makePeriodStat($periods[$key]);
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function makePeriodStat(array $period): Stat
    {
        $label = $period['label'] ?? '';
        $value = $period['score_average'] ?? null;

        $stat = Stat::make($label, $value !== null ? number_format((float) $value, 2) : '—');

        $description = $this->buildDescription($period);
        if ($description !== '') {
            $stat->description($description);
        }

        $delta = $period['score_delta'] ?? null;

        if ($delta !== null) {
            $stat->color($delta > 0 ? 'success' : ($delta < 0 ? 'danger' : 'gray'));
        } else {
            $stat->color('gray');
        }

        return $stat;
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function buildDescription(array $period): string
    {
        $segments = [];

        $delta = $period['score_delta'] ?? null;
        $comparison = $period['comparison_label'] ?? '';

        if ($delta !== null) {
            $segments[] = __('analytics.widgets.ssr_stats.periods.delta', [
                'delta' => $this->formatDelta($delta),
                'comparison' => $comparison,
            ]);
        } else {
            $segments[] = __('analytics.widgets.ssr_stats.periods.delta_unavailable');
        }

        $samples = (int) ($period['score_samples'] ?? 0);
        $segments[] = trans_choice('analytics.widgets.ssr_stats.periods.samples', $samples, [
            'count' => number_format($samples),
        ]);

        if (($period['first_byte_average'] ?? null) !== null) {
            $segments[] = $this->formatFirstByteSegment($period);
        } else {
            $segments[] = __('analytics.widgets.ssr_stats.periods.first_byte_unavailable');
        }

        $range = $period['range'] ?? null;
        if (is_array($range) && isset($range['start'], $range['end'])) {
            $segments[] = __('analytics.widgets.ssr_stats.periods.range', [
                'start' => $range['start'],
                'end' => $range['end'],
            ]);
        }

        return implode(' • ', array_filter($segments));
    }

    private function formatDelta(float $delta): string
    {
        return ($delta > 0 ? '+' : '').number_format($delta, 2);
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function formatFirstByteSegment(array $period): string
    {
        $value = (float) $period['first_byte_average'];
        $base = __('analytics.widgets.ssr_stats.periods.first_byte.label', [
            'value' => number_format($value, 2),
        ]);

        $delta = $period['first_byte_delta'] ?? null;
        if ($delta === null) {
            return $base;
        }

        return $base.' '.__('analytics.widgets.ssr_stats.periods.first_byte.delta', [
            'delta' => $this->formatDelta((float) $delta),
            'comparison' => $period['comparison_label'] ?? '',
        ]);
    }
}
