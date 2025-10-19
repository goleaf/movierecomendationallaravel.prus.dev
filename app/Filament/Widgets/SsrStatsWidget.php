<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\SsrMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SsrStatsWidget extends BaseWidget
{
    public function __construct(private readonly SsrMetricsService $metrics)
    {
        parent::__construct();
    }

    protected function getStats(): array
    {
        $headline = $this->metrics->headline();

        return [
            Stat::make($headline['label'], (string) $headline['score'])
                ->description($headline['description']),
        ];
    }
}
