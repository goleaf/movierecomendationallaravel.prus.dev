<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CtrSvgController extends Controller
{
    public function line(Request $r): Response
    {
        $from = $r->query('from', now()->subDays(14)->format('Y-m-d'));
        $to = $r->query('to', now()->format('Y-m-d'));

        $logs = DB::table('rec_ab_logs')
            ->selectRaw('date(created_at) as d, variant, count(*) as imps')
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->groupBy('d', 'variant')
            ->get();

        $clicks = DB::table('rec_clicks')
            ->selectRaw('date(created_at) as d, variant, count(*) as clks')
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->groupBy('d', 'variant')
            ->get();

        $days = [];
        $cur = strtotime($from);
        $end = strtotime($to);
        while ($cur <= $end) {
            $days[] = date('Y-m-d', $cur);
            $cur = strtotime('+1 day', $cur);
        }

        $series = ['A' => [], 'B' => []];
        foreach ($days as $d) {
            $impsA = optional($logs->where('d', $d)->where('variant', 'A')->first())->imps ?? 0;
            $impsB = optional($logs->where('d', $d)->where('variant', 'B')->first())->imps ?? 0;
            $clksA = optional($clicks->where('d', $d)->where('variant', 'A')->first())->clks ?? 0;
            $clksB = optional($clicks->where('d', $d)->where('variant', 'B')->first())->clks ?? 0;
            $series['A'][] = ['d' => $d, 'ctr' => $impsA > 0 ? (100.0 * $clksA / $impsA) : 0.0];
            $series['B'][] = ['d' => $d, 'ctr' => $impsB > 0 ? (100.0 * $clksB / $impsB) : 0.0];
        }

        $w = 720;
        $h = 260;
        $pad = 40;
        $maxY = 0.0;
        foreach (['A', 'B'] as $v) {
            foreach ($series[$v] as $pt) {
                $maxY = max($maxY, (float) $pt['ctr']);
            }
        }
        $maxY = max(5.0, ceil($maxY / 5.0) * 5.0);

        $map = function (array $arr) use ($w, $h, $pad, $maxY): string {
            $n = max(1, count($arr) - 1);
            $pts = [];
            foreach ($arr as $i => $pt) {
                $x = $pad + $i * ($w - 2 * $pad) / $n;
                $y = $h - $pad - ((float) $pt['ctr'] / $maxY) * ($h - 2 * $pad);
                $pts[] = sprintf('%.1f,%.1f', $x, $y);
            }

            return implode(' ', $pts);
        };

        $grid = '';
        for ($i = 0; $i <= 5; $i++) {
            $y = $pad + $i * ($h - 2 * $pad) / 5;
            $val = round($maxY - $i * $maxY / 5, 1);
            $grid .= '<line x1="'.$pad.'" y1="'.($y).'" x2="'.($w - $pad).'" y2="'.($y).'" stroke="#1d2229" stroke-width="1"/>';
            $grid .= '<text x="5" y="'.($y + 4).'" fill="#889" font-size="10">'.$val.'%</text>';
        }

        $title = __('analytics.svg.ctr_line_title');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'">'
            .'<rect x="0" y="0" width="'.$w.'" height="'.$h.'" fill="#0b0c0f"/>'
            .$grid
            .'<polyline fill="none" stroke="#5aa0ff" stroke-width="2" points="'.$map($series['A']).'"/>'
            .'<polyline fill="none" stroke="#8ee38b" stroke-width="2" points="'.$map($series['B']).'"/>'
            .'<text x="10" y="16" fill="#ddd">'.e($title).'</text>'
            .'</svg>';

        return response($svg)->header('Content-Type','image/svg+xml');
    }
}
