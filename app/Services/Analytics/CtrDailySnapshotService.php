<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\CtrDailySnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CtrDailySnapshotService
{
    public function aggregateRange(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $current = $from->startOfDay();
        $end = $to->startOfDay();

        while ($current->lessThanOrEqualTo($end)) {
            $this->aggregateForDate($current);
            $current = $current->addDay();
        }
    }

    public function aggregateForDate(CarbonImmutable $day): void
    {
        if (! Schema::hasTable('rec_ab_logs') || ! Schema::hasTable('rec_clicks')) {
            return;
        }

        $start = $day->startOfDay();
        $end = $day->endOfDay();

        $impressions = DB::table('rec_ab_logs')
            ->select('variant', DB::raw('count(*) as total'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('variant')
            ->pluck('total', 'variant')
            ->map(fn ($value) => (int) $value)
            ->all();

        $clicks = DB::table('rec_clicks')
            ->select('variant', DB::raw('count(*) as total'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('variant')
            ->pluck('total', 'variant')
            ->map(fn ($value) => (int) $value)
            ->all();

        $variants = array_values(array_unique(array_merge(array_keys($impressions), array_keys($clicks))));
        if ($variants === []) {
            $variants = ['A', 'B'];
        }

        $snapshotDate = $day->startOfDay();

        foreach ($variants as $variant) {
            $variantImpressions = (int) ($impressions[$variant] ?? 0);
            $variantClicks = (int) ($clicks[$variant] ?? 0);

            $views = 0;

            $ctr = $variantImpressions > 0 ? round(100 * $variantClicks / $variantImpressions, 4) : 0.0;
            $viewRate = $views > 0 ? round(100 * $variantClicks / $views, 4) : 0.0;

            CtrDailySnapshot::query()->updateOrCreate(
                [
                    'snapshot_date' => $snapshotDate,
                    'variant' => $variant,
                ],
                [
                    'impressions' => $variantImpressions,
                    'clicks' => $variantClicks,
                    'views' => $views,
                    'ctr' => $ctr,
                    'view_rate' => $viewRate,
                ]
            );
        }
    }
}
