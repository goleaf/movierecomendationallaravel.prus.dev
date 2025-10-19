<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\SsrMetricsService;
use Filament\Widgets\ChartWidget;

class SsrScoreWidget extends ChartWidget
{
    public function __construct(private readonly SsrMetricsService $metrics)
    {
        parent::__construct();
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
        return $this->metrics->trend();
    }
}
