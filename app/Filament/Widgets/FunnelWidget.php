<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FunnelWidget extends Widget
{
    protected static string $view = 'filament.widgets.funnel';

    protected static ?string $heading = 'Funnels (7 дней)';

    protected function getViewData(): array
    {
        $from = now()->subDays(7)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $placementVariantImpressions = Schema::hasTable('rec_ab_logs')
            ? DB::table('rec_ab_logs')
                ->selectRaw('placement, variant, count(*) as imps')
                ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                ->groupBy('placement', 'variant')
                ->get()
                ->groupBy('placement')
                ->map(static function ($rows) {
                    return $rows
                        ->pluck('imps', 'variant')
                        ->map(static fn ($value) => (int) $value)
                        ->all();
                })
                ->all()
            : [];

        $placementImps = [];
        foreach ($placementVariantImpressions as $placement => $variants) {
            $placementImps[$placement] = array_sum($variants);
        }

        $totalViews = Schema::hasTable('device_history')
            ? (int) DB::table('device_history')
                ->whereBetween('viewed_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                ->count()
            : 0;

        $clicksPerPlacement = Schema::hasTable('rec_clicks')
            ? DB::table('rec_clicks')
                ->selectRaw('placement, count(*) as clks')
                ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                ->groupBy('placement')
                ->pluck('clks', 'placement')
                ->map(static fn ($value) => (int) $value)
                ->all()
            : [];

        $rows = [];
        $placements = ['home', 'show', 'trends'];
        foreach ($placements as $placement) {
            $imps = $placementImps[$placement] ?? 0;
            $clicks = $clicksPerPlacement[$placement] ?? 0;

            $rows[] = [
                'label' => $placement,
                'imps' => $imps,
                'clicks' => $clicks,
                'views' => $totalViews,
                'ctr' => $imps > 0 ? round(100 * $clicks / $imps, 2) : 0.0,
                'view_rate' => $totalViews > 0 ? round(100 * $clicks / $totalViews, 2) : 0.0,
            ];
        }

        $totalImps = array_sum(array_column($rows, 'imps'));
        $totalClicks = array_sum(array_column($rows, 'clicks'));

        $rows[] = [
            'label' => 'Итого',
            'imps' => $totalImps,
            'clicks' => $totalClicks,
            'views' => $totalViews,
            'ctr' => $totalImps > 0 ? round(100 * $totalClicks / $totalImps, 2) : 0.0,
            'view_rate' => $totalViews > 0 ? round(100 * $totalClicks / $totalViews, 2) : 0.0,
        ];

        return [
            'heading' => static::$heading,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ];
    }
}
