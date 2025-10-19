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

        $impVariant = $logs->select('variant', DB::raw('count(*) as imps'))->groupBy('variant')->pluck('imps', 'variant')->all();
        $clkVariant = $clicks->select('variant', DB::raw('count(*) as clks'))->groupBy('variant')->pluck('clks', 'variant')->all();

        $logsPlacement = DB::table('rec_ab_logs')->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"]);
        if ($variant) {
            $logsPlacement->where('variant', $variant);
        }

        $impPlacement = $logsPlacement
            ->select('placement', DB::raw('count(*) as imps'))
            ->groupBy('placement')
            ->pluck('imps', 'placement')
            ->all();

        $viewPlacement = DB::table('device_history')
            ->whereBetween('viewed_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->select('page', DB::raw('count(*) as views'))
            ->groupBy('page')
            ->pluck('views', 'page')
            ->all();

        $summary = [];
        foreach (['A', 'B'] as $v) {
            $imps = (int) ($impVariant[$v] ?? 0);
            $clks = (int) ($clkVariant[$v] ?? 0);
            $summary[] = ['v' => $v, 'imps' => $imps, 'clks' => $clks, 'ctr' => $imps > 0 ? round(100 * $clks / $imps, 2) : 0.0];
        }

        $clicksP = DB::table('rec_clicks')->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->select('placement', DB::raw('count(*) as clks'))->groupBy('placement')->pluck('clks', 'placement')->all();

        $totalImps = array_sum($impPlacement);
        $totalViews = array_sum($viewPlacement);
        $funnels = [];
        foreach (['home', 'show', 'trends'] as $pl) {
            $clks = (int) DB::table('rec_clicks')->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])->where('placement', $pl)->count();
            $imps = (int) ($impPlacement[$pl] ?? 0);
            $views = (int) ($viewPlacement[$pl] ?? 0);
            $funnels[$pl] = ['imps' => $imps, 'clks' => $clks, 'views' => $views];
        }
        $funnels['Итого'] = ['imps' => $totalImps, 'clks' => (int) DB::table('rec_clicks')->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])->count(), 'views' => $totalViews];

        // z-test data
        return view('admin.ctr', compact('from', 'to', 'placement', 'variant', 'summary', 'clicksP', 'funnels', 'impVariant', 'clkVariant'))
            ->with('funnelGenres',[])->with('funnelYears',[]);
    }
}
