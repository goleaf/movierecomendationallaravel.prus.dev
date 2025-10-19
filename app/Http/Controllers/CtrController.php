<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CtrController extends Controller
{
    public function index(Request $r): View
    {
        $from = $r->query('from', now()->subDays(7)->format('Y-m-d'));
        $to = $r->query('to', now()->format('Y-m-d'));
        $placement = $r->query('p');
        $variant = $r->query('v');

        $logs = DB::table('rec_ab_logs')->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
        $clicks = DB::table('rec_clicks')->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);

        if ($placement) {
            $clicks->where('placement', $placement);
        }

        if ($variant) {
            $logs->where('variant', $variant);
            $clicks->where('variant', $variant);
        }

        $impVariant = (clone $logs)
            ->select('variant', DB::raw('count(*) as imps'))
            ->groupBy('variant')
            ->pluck('imps', 'variant')
            ->map(static fn ($value) => (int) $value)
            ->all();

        $placementVariantImpressions = (clone $logs)
            ->select('placement', 'variant', DB::raw('count(*) as imps'))
            ->groupBy('placement', 'variant')
            ->get()
            ->groupBy('placement')
            ->map(static function ($rows) {
                return $rows
                    ->pluck('imps', 'variant')
                    ->map(static fn ($value) => (int) $value)
                    ->all();
            })
            ->all();

        $placementImpressions = [];
        foreach ($placementVariantImpressions as $placementKey => $variantCounts) {
            $placementImpressions[$placementKey] = array_sum($variantCounts);
        }

        $clkVariant = (clone $clicks)
            ->select('variant', DB::raw('count(*) as clks'))
            ->groupBy('variant')
            ->pluck('clks', 'variant')
            ->map(static fn ($value) => (int) $value)
            ->all();

        $clicksP = (clone $clicks)
            ->select('placement', DB::raw('count(*) as clks'))
            ->groupBy('placement')
            ->pluck('clks', 'placement')
            ->map(static fn ($value) => (int) $value)
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

        $totalViews = (int) DB::table('device_history')
            ->whereBetween('viewed_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->count();

        $funnels = [];
        $funnelPlacements = ['home', 'show', 'trends'];
        $totalImps = 0;
        $totalClicks = 0;

        foreach ($funnelPlacements as $funnelPlacement) {
            $imps = $placementImpressions[$funnelPlacement] ?? 0;
            $clks = $clicksP[$funnelPlacement] ?? 0;

            $totalImps += $imps;
            $totalClicks += $clks;

            $funnels[$funnelPlacement] = [
                'imps' => $imps,
                'clks' => $clks,
                'views' => $totalViews,
            ];
        }

        $funnels['Итого'] = [
            'imps' => $totalImps,
            'clks' => $totalClicks,
            'views' => $totalViews,
        ];

        // z-test data
        return view('admin.ctr', compact('from', 'to', 'placement', 'variant', 'summary', 'clicksP', 'funnels', 'impVariant', 'clkVariant'))
            ->with('funnelGenres', [])->with('funnelYears', []);
    }
}
