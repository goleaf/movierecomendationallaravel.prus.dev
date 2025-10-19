<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ZTestWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $from = now()->subDays(7)->toDateTimeString();
        $to = now()->toDateTimeString();

        $impressions = DB::table('rec_ab_logs')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('variant,count(*) c')
            ->groupBy('variant')
            ->pluck('c', 'variant')
            ->all();

        $clicks = DB::table('rec_clicks')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('variant,count(*) c')
            ->groupBy('variant')
            ->pluck('c', 'variant')
            ->all();

        $impressionsA = (int) ($impressions['A'] ?? 0);
        $impressionsB = (int) ($impressions['B'] ?? 0);
        $clicksA = (int) ($clicks['A'] ?? 0);
        $clicksB = (int) ($clicks['B'] ?? 0);

        $ctrA = $impressionsA > 0 ? $clicksA / $impressionsA : 0;
        $ctrB = $impressionsB > 0 ? $clicksB / $impressionsB : 0;
        $pooled = ($clicksA + $clicksB) / max(1, ($impressionsA + $impressionsB));
        $z = ($ctrA - $ctrB) / max(1e-9, sqrt($pooled * (1 - $pooled) * (1 / max(1, $impressionsA) + 1 / max(1, $impressionsB))));

        $aDescription = __('analytics.widgets.z_test.description_format', [
            'impressions' => __('analytics.widgets.z_test.impressions', ['count' => number_format($impressionsA)]),
            'clicks' => __('analytics.widgets.z_test.clicks', ['count' => number_format($clicksA)]),
        ]);

        $bDescription = __('analytics.widgets.z_test.description_format', [
            'impressions' => __('analytics.widgets.z_test.impressions', ['count' => number_format($impressionsB)]),
            'clicks' => __('analytics.widgets.z_test.clicks', ['count' => number_format($clicksB)]),
        ]);

        $pValueDescription = abs($z) > 1.96
            ? __('analytics.widgets.z_test.p_value.significant')
            : __('analytics.widgets.z_test.p_value.not_significant');

        return [
            Stat::make(__('analytics.widgets.z_test.ctr_a'), round($ctrA * 100, 2).'%')
                ->description($aDescription),
            Stat::make(__('analytics.widgets.z_test.ctr_b'), round($ctrB * 100, 2).'%')
                ->description($bDescription),
            Stat::make(__('analytics.widgets.z_test.z_test'), number_format($z, 2))
                ->description($pValueDescription),
        ];
    }
}
