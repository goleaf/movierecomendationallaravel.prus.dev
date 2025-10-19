<?php

namespace App\Filament\Widgets;

use App\Models\DeviceHistory;
use App\Models\RecAbLog;
use App\Models\RecClick;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;

class FunnelWidget extends Widget
{
    protected static string $view = 'filament.widgets.funnel';

    protected static ?string $heading = 'Funnels (7 дней)';

    protected function getViewData(): array
    {
        $from = now()->subDays(7)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $impPlacement = Schema::hasTable('rec_ab_logs')
            ? DB::table('rec_ab_logs')
                ->selectRaw('placement, count(*) as imps')
                ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                ->groupBy('placement')
                ->pluck('imps', 'placement')
                ->all()
            : [];

        $totalImps = array_sum($impPlacement);

        $viewPlacement = Schema::hasTable('device_history')
            ? DB::table('device_history')
                ->selectRaw('page, count(*) as views')
                ->whereBetween('viewed_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                ->groupBy('page')
                ->pluck('views', 'page')
                ->all()
            : [];

        $totalViews = array_sum($viewPlacement);

        $totalClicks = Schema::hasTable('rec_clicks')
            ? (int) RecClick::query()
                ->betweenCreatedAt("{$from} 00:00:00", "{$to} 23:59:59")
                ->count()
            : 0;

        $rows = [];
        $placements = ['home', 'show', 'trends'];
        foreach ($placements as $placement) {
            $clicks = Schema::hasTable('rec_clicks')
                ? (int) RecClick::query()
                    ->where('placement', $placement)
                    ->betweenCreatedAt("{$from} 00:00:00", "{$to} 23:59:59")
                    ->count()
                : 0;

            $rows[] = [
                'label' => $placement,
                'imps' => (int) ($impPlacement[$placement] ?? 0),
                'clicks' => $clicks,
                'views' => (int) ($viewPlacement[$placement] ?? 0),
                'ctr' => ((int) ($impPlacement[$placement] ?? 0)) > 0 ? round(100 * $clicks / (int) $impPlacement[$placement], 2) : 0.0,
                'view_rate' => ((int) ($viewPlacement[$placement] ?? 0)) > 0 ? round(100 * $clicks / (int) $viewPlacement[$placement], 2) : 0.0,
            ];
        }

        $rows[] = [
            'label' => __('analytics.widgets.funnel.total'),
            'imps' => $totalImps,
            'clicks' => $totalClicks,
            'views' => $totalViews,
            'ctr' => $totalImps > 0 ? round(100 * $totalClicks / $totalImps, 2) : 0.0,
            'view_rate' => $totalViews > 0 ? round(100 * $totalClicks / $totalViews, 2) : 0.0,
        ];

        return [
            'heading' => $this->getHeading(),
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ];
    }
}
