<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\SsrMetricsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Storage;

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
        $labels = [];
        $series = [];

        /** @var SsrMetricsService $metrics */
        $metrics = app(SsrMetricsService::class);

        if ($metrics->hasMetrics()) {
            $trend = $metrics->scoreTrend();

            foreach ($trend as $row) {
                $labels[] = $row['recorded_date'];
                $series[] = $row['average_score'];
            }
        } elseif (Storage::exists('metrics/last.json')) {
            $json = json_decode(Storage::get('metrics/last.json'), true) ?: [];
            $labels[] = now()->toDateString();
            $avg = 0;
            $n = 0;

            foreach ($json as $row) {
                $avg += (int) ($row['score'] ?? 0);
                $n++;
            }

            $series[] = $n ? round($avg / $n, 2) : 0;
        }

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
