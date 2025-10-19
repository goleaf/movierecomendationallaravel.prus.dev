<?php

namespace App\Http\Controllers;

use App\Models\DeviceHistory;
use App\Models\RecAbLog;
use App\Models\RecClick;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CtrController extends Controller
{
    public function index(Request $r): View
    {
        $from = $r->query('from', now()->subDays(7)->format('Y-m-d'));
        $to = $r->query('to', now()->format('Y-m-d'));
        $placement = $r->query('p');
        $variant = $r->query('v');

        $fromAt = "{$from} 00:00:00";
        $toAt = "{$to} 23:59:59";

        $logsQuery = RecAbLog::query()
            ->betweenCreatedAt($fromAt, $toAt)
            ->forVariant($variant);

        $clicksQuery = RecClick::query()
            ->betweenCreatedAt($fromAt, $toAt)
            ->forVariant($variant)
            ->forPlacement($placement);

        $impVariant = (clone $logsQuery)
            ->selectRaw('variant, count(*) as imps')
            ->groupBy('variant')
            ->pluck('imps', 'variant')
            ->map(fn ($count) => (int) $count)
            ->all();

        $clkVariant = (clone $clicksQuery)
            ->selectRaw('variant, count(*) as clks')
            ->groupBy('variant')
            ->pluck('clks', 'variant')
            ->map(fn ($count) => (int) $count)
            ->all();

        $summary = [];
        foreach (['A', 'B'] as $v) {
            $imps = $impVariant[$v] ?? 0;
            $clks = $clkVariant[$v] ?? 0;
            $summary[] = [
                'v' => $v,
                'imps' => $imps,
                'clks' => $clks,
                'ctr' => $imps > 0 ? round(100 * $clks / $imps, 2) : 0.0,
            ];
        }

        $clicksP = RecClick::query()
            ->betweenCreatedAt($fromAt, $toAt)
            ->selectRaw('placement, count(*) as clks')
            ->groupBy('placement')
            ->pluck('clks', 'placement')
            ->map(fn ($count) => (int) $count)
            ->all();

        $totalImps = array_sum($impVariant);
        $totalViews = DeviceHistory::query()
            ->betweenViewedAt($fromAt, $toAt)
            ->count();

        $funnels = [];
        foreach (['home', 'show', 'trends'] as $pl) {
            $clks = (int) ($clicksP[$pl] ?? 0);

            $funnels[$pl] = [
                'imps' => $totalImps,
                'clks' => $clks,
                'views' => $totalViews,
            ];
        }

        $funnels['Итого'] = [
            'imps' => $totalImps,
            'clks' => array_sum($clicksP),
            'views' => $totalViews,
        ];

        // z-test data
        return view('admin.ctr', compact('from', 'to', 'placement', 'variant', 'summary', 'clicksP', 'funnels', 'impVariant', 'clkVariant'))
            ->with('funnelGenres', [])->with('funnelYears', []);
    }
}
