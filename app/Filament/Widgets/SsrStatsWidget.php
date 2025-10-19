<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SsrStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $score = 0;
        $paths = 0;

        if (Schema::hasTable('ssr_metrics')) {
            $timestampColumn = Schema::hasColumn('ssr_metrics', 'collected_at') ? 'collected_at' : 'created_at';

            $row = DB::table('ssr_metrics')
                ->orderByDesc($timestampColumn)
                ->orderByDesc('id')
                ->first();

            if ($row) {
                $score = (int) $row->score;
                $paths = DB::table('ssr_metrics')->distinct('path')->count('path');
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
