<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\SsrMetricsService;
use Filament\Widgets\ChartWidget;

class SsrScoreWidget extends ChartWidget
{
    private SsrMetricsService $ssrMetricsService;

    public function boot(SsrMetricsService $ssrMetricsService): void
    {
        $this->ssrMetricsService = $ssrMetricsService;
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
        $averages = ($this->ssrMetricsService ??= app(SsrMetricsService::class))->dailyAverageScores();
        $labels = array_map(static fn (array $row): string => $row['date'], $averages);
        $series = array_map(static fn (array $row): float => $row['average'], $averages);

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
