<?php

namespace App\Http\Controllers;

use App\Models\RecAbLog;
use App\Models\RecClick;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CtrSvgBarsController extends Controller
{
    public function bars(Request $r): Response
    {
        $from = $r->query('from', now()->subDays(7)->format('Y-m-d'));
        $to = $r->query('to', now()->format('Y-m-d'));

        $clicks = DB::table('rec_clicks')
            ->selectRaw('placement, variant, count(*) as c')
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->groupBy('placement', 'variant')
            ->get();

        $impsVar = DB::table('rec_ab_logs')
            ->selectRaw('placement, variant, count(*) as c')
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->groupBy('placement', 'variant')
            ->get();

        $placements = ['home', 'show', 'trends'];
        $variants = ['A', 'B'];
        $bars = [];
        foreach ($placements as $p) {
            foreach ($variants as $v) {
                $row = $clicks->where('placement', $p)->where('variant', $v)->first();
                $clks = (int) ($row->c ?? 0);
                $impRow = $impsVar->where('placement', $p)->where('variant', $v)->first();
                $imps = (int) ($impRow->c ?? 0);
                $ctr = $imps > 0 ? 100.0 * $clks / $imps : 0.0;
                $bars[] = ['label' => "$p-$v", 'ctr' => $ctr];
            }
        }

        $w = 720;
        $h = 260;
        $pad = 40;
        $barw = 24;
        $gap = 18;
        $maxY = 5.0;
        foreach ($bars as $b) {
            $maxY = max($maxY, ceil($b['ctr'] / 5.0) * 5.0);
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'">'
             .'<rect x="0" y="0" width="'.$w.'" height="'.$h.'" fill="#0b0c0f"/>';

        for ($i = 0; $i <= 5; $i++) {
            $y = $pad + $i * ($h - 2 * $pad) / 5;
            $val = round($maxY - $i * $maxY / 5, 1);
            $svg .= '<line x1="'.$pad.'" y1="'.($y).'" x2="'.($w - $pad).'" y2="'.($y).'" stroke="#1d2229" stroke-width="1"/>';
            $svg .= '<text x="5" y="'.($y + 4).'" fill="#889" font-size="10">'.$val.'%</text>';
        }

        $x = $pad + 10;
        foreach ($bars as $i => $b) {
            $hbar = ($h - 2 * $pad) * ($b['ctr'] / max(1.0, $maxY));
            $x1 = $x + $i * ($barw + $gap);
            $y1 = $h - $pad - $hbar;
            $color = (str_contains($b['label'], '-A')) ? '#5aa0ff' : '#8ee38b';
            $svg .= '<rect x="'.$x1.'" y="'.$y1.'" width="'.$barw.'" height="'.$hbar.'" fill="'.$color.'"/>';
            $svg .= '<text x="'.($x1).'" y="'.($h - $pad + 12).'" fill="#aaa" font-size="10" transform="rotate(45 '.($x1).','.($h - $pad + 12).')">'.$b['label'].'</text>';
        }

        $svg .= '<text x="10" y="16" fill="#ddd">CTR по площадкам (A — синий, B — зелёный)</text></svg>';

        return response($svg)->header('Content-Type', 'image/svg+xml');
    }
}
