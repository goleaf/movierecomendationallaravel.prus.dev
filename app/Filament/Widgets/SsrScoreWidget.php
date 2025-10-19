<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\SsrMetricsService;
use Filament\Widgets\ChartWidget;

class SsrScoreWidget extends ChartWidget
{
    public function getHeading(): ?string
    {
        $summary = app(SsrMetricsService::class)->summary();
        $delta = (float) $summary['delta'];
        $deltaFormatted = ($delta >= 0 ? '+' : '').number_format($delta, 2);

        return __('analytics.widgets.ssr_score.heading', ['delta' => $deltaFormatted]);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trend = app(SsrMetricsService::class)->trend();
        $labels = $trend->pluck('date')->all();
        $series = $trend->pluck('score')->all();

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
}
