<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\SsrMetricsService;
use Filament\Widgets\ChartWidget;

class SsrScoreWidget extends ChartWidget
{
    private SsrMetricsService $metrics;

    public function boot(SsrMetricsService $metrics): void
    {
        $this->metrics = $metrics;
    }

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
        $trend = $this->metrics->getScoreTrend();

        return [
            'datasets' => [
                [
                    'label' => __('analytics.widgets.ssr_score.dataset'),
                    'data' => $trend['series'],
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }
}
