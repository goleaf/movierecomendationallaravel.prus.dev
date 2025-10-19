<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\SsrMetricsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Storage;

class SsrStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $score = 0;
        $paths = 0;

        /** @var SsrMetricsService $metrics */
        $metrics = app(SsrMetricsService::class);

        if ($metrics->hasMetrics()) {
            $summary = $metrics->latestSummary();

            if ($summary !== null) {
                $score = (int) round($summary['average_score']);
                $paths = $summary['path_count'];
            }
        } elseif (Storage::exists('metrics/last.json')) {
            $json = json_decode(Storage::get('metrics/last.json'), true) ?: [];
            $paths = count($json);

            foreach ($json as $r) {
                $score += (int) ($r['score'] ?? 0);
            }

            if ($paths > 0) {
                $score = (int) round($score / $paths);
            }
        }

        $description = trans_choice(
            'analytics.widgets.ssr_stats.description',
            $paths,
            ['count' => number_format($paths)]
        );

        return [
            Stat::make(__('analytics.widgets.ssr_stats.label'), (string) $score)
                ->description($description),
        ];
    }
}
