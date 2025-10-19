<?php

namespace App\Filament\Widgets;

use App\Models\RecAbLog;
use App\Models\RecClick;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ZTestWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $from = now()->subDays(7)->toDateTimeString();
        $to = now()->toDateTimeString();
        $imp = RecAbLog::query()->betweenCreatedAt($from, $to)->selectRaw('variant,count(*) c')->groupBy('variant')->pluck('c', 'variant')->all();
        $clk = RecClick::query()->betweenCreatedAt($from, $to)->selectRaw('variant,count(*) c')->groupBy('variant')->pluck('c', 'variant')->all();
        $Ai = (int) ($imp['A'] ?? 0);
        $Bi = (int) ($imp['B'] ?? 0);
        $Ac = (int) ($clk['A'] ?? 0);
        $Bc = (int) ($clk['B'] ?? 0);
        $p1 = $Ai > 0 ? $Ac / $Ai : 0;
        $p2 = $Bi > 0 ? $Bc / $Bi : 0;
        $p = ($Ac + $Bc) / max(1, ($Ai + $Bi));
        $z = ($p1 - $p2) / max(1e-9, sqrt($p * (1 - $p) * (1 / max(1, $Ai) + 1 / max(1, $Bi))));

        return [Stat::make('CTR A', round($p1 * 100, 2).'%')->description("Imps:$Ai Clicks:$Ac"),
            Stat::make('CTR B', round($p2 * 100, 2).'%')->description("Imps:$Bi Clicks:$Bc"),
            Stat::make('Z-test', number_format($z, 2))->description(abs($z) > 1.96 ? 'p < 0.05' : 'p ≥ 0.05'), ];
    }
}
