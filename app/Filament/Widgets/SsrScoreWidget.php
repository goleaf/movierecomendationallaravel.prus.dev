<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\SsrMetricsService;
use Filament\Widgets\ChartWidget;

class SsrScoreWidget extends ChartWidget
{
    public function getHeading(): ?string
    {
        return __('analytics.widgets.ssr_score.heading');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trend = app(SsrMetricsService::class)->scoreTrend();

        return [
            'datasets' => [
                [
                    'label' => __('analytics.widgets.ssr_score.dataset'),
                    'data' => $trend['values'],
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }
}
