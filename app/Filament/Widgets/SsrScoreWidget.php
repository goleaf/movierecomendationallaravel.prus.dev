<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\SsrMetricsAggregator;
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
        return app(SsrMetricsAggregator::class)->trend();
    }
}
