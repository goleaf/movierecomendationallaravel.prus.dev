<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\SsrAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SsrStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $headline = app(SsrAnalyticsService::class)->headline();
        $periods = $headline['periods'] ?? [];

        $stats = [];

        foreach (['today', 'yesterday', 'seven_days'] as $periodKey) {
            if (! isset($periods[$periodKey])) {
                continue;
            }

            /** @var array{score: float, first_byte_ms: float, samples: int, paths: int, delta?: array{score: float, first_byte_ms: float, samples: int, paths: int}, range?: array{from: string, to: string}} $period */
            $period = $periods[$periodKey];

            $label = __('analytics.widgets.ssr_stats.periods.'.$periodKey.'.label');
            $value = number_format((float) ($period['score'] ?? 0), 2);

            $descriptionParts = [];

            if (isset($period['delta'])) {
                $descriptionParts[] = __('analytics.widgets.ssr_stats.delta.score', [
                    'value' => $this->formatDelta((float) ($period['delta']['score'] ?? 0)),
                ]);
                $descriptionParts[] = __('analytics.widgets.ssr_stats.delta.first_byte', [
                    'value' => $this->formatDelta((float) ($period['delta']['first_byte_ms'] ?? 0), 0),
                ]);
                $descriptionParts[] = __('analytics.widgets.ssr_stats.delta.paths', [
                    'value' => $this->formatDelta((float) ($period['delta']['paths'] ?? 0), 0),
                ]);
                $descriptionParts[] = __('analytics.widgets.ssr_stats.delta.samples', [
                    'value' => $this->formatDelta((float) ($period['delta']['samples'] ?? 0), 0),
                ]);
            }

            $descriptionParts[] = trans_choice('analytics.widgets.ssr_stats.paths', (int) ($period['paths'] ?? 0), [
                'count' => number_format((int) ($period['paths'] ?? 0)),
            ]);

            $descriptionParts[] = trans_choice('analytics.widgets.ssr_stats.samples', (int) ($period['samples'] ?? 0), [
                'count' => number_format((int) ($period['samples'] ?? 0)),
            ]);

            $descriptionParts[] = __('analytics.widgets.ssr_stats.first_byte', [
                'value' => number_format((float) ($period['first_byte_ms'] ?? 0), 0),
            ]);

            if ($periodKey === 'seven_days' && isset($period['range'])) {
                $descriptionParts[] = __('analytics.widgets.ssr_stats.periods.seven_days.range', [
                    'from' => $period['range']['from'] ?? '',
                    'to' => $period['range']['to'] ?? '',
                ]);
            }

            $description = implode(' • ', array_filter($descriptionParts));

            $stat = Stat::make($label, $value)
                ->description($description);

            if (isset($period['delta'])) {
                $scoreDelta = (float) ($period['delta']['score'] ?? 0);

                if ($scoreDelta > 0) {
                    $stat = $stat->color('success');
                } elseif ($scoreDelta < 0) {
                    $stat = $stat->color('danger');
                }
            }

            $stats[] = $stat;
        }

        return $stats;
    }

    private function formatDelta(float $value, int $precision = 2): string
    {
        if (abs($value) < pow(10, -$precision) / 2) {
            return '±'.number_format(0, $precision, '.', '');
        }

        $formatted = number_format(abs($value), $precision, '.', '');
        $sign = $value > 0 ? '+' : '-';

        return $sign.$formatted;
    }
}
