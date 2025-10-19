<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FunnelWidget extends Widget
{
    protected static string $view = 'filament.widgets.funnel';

    public function getHeading(): ?string
    {
        return __('analytics.widgets.funnel.heading');
    }

    protected function getViewData(): array
    {
        $from = now()->subDays(7)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $impVariant = Schema::hasTable('rec_ab_logs')
            ? DB::table('rec_ab_logs')
                ->selectRaw('variant, count(*) as imps')
                ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                ->groupBy('variant')
                ->pluck('imps', 'variant')
                ->all()
            : [];

        $totalImps = array_sum($impVariant);

        $totalViews = Schema::hasTable('device_history')
            ? (int) DB::table('device_history')
                ->whereBetween('viewed_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                ->count()
            : 0;

        $totalClicks = Schema::hasTable('rec_clicks')
            ? (int) DB::table('rec_clicks')
                ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                ->count()
            : 0;

        $rows = [];
        $placements = ['home', 'show', 'trends'];
        foreach ($placements as $placement) {
            $clicks = Schema::hasTable('rec_clicks')
                ? (int) DB::table('rec_clicks')
                    ->where('placement', $placement)
                    ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                    ->count()
                : 0;

            $rows[] = [
                'label' => __('analytics.widgets.funnel.placements.'.$placement),
                'imps' => $totalImps,
                'clicks' => $clicks,
                'views' => $totalViews,
                'ctr' => $totalImps > 0 ? round(100 * $clicks / $totalImps, 2) : 0.0,
                'view_rate' => $totalViews > 0 ? round(100 * $clicks / $totalViews, 2) : 0.0,
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
