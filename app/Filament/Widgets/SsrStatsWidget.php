<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SsrStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $score=0; $paths=0;
        if (\Schema::hasTable('ssr_metrics')) {
            $row=DB::table('ssr_metrics')->orderByDesc('id')->first();
            if($row){ $score=(int)$row->score; $paths=1; }
        } elseif (Storage::exists('metrics/last.json')) {
            $json=json_decode(Storage::get('metrics/last.json'),true) ?: [];
            $paths=count($json); foreach($json as $r)$score+=(int)($r['score']??0); if($paths)$score=(int)round($score/$paths);
        }
        return [ Stat::make('SSR Score', (string)$score)->description($paths.' path(s)') ];
    }
}
