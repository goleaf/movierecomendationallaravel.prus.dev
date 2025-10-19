<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\SsrMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SsrStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $summary = app(SsrMetricsService::class)->latestSummary();
        $score = (int) ($summary['score'] ?? 0);
        $paths = (int) ($summary['path_count'] ?? 0);
        $capturedAt = $summary['captured_at'] ?? null;

        $description = trans_choice(
            'analytics.widgets.ssr_stats.description',
            $paths,
            ['count' => number_format($paths)]
        );

        if ($capturedAt instanceof Carbon) {
            $description .= ' · '.$capturedAt->diffForHumans();
        }

        return [
            Stat::make(__('analytics.widgets.ssr_stats.label'), (string) $score)
                ->description($description),
        ];
    }
}
