<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SsrIssuesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $issues=[];
        if (\Schema::hasTable('ssr_metrics')) {
            $rows=DB::table('ssr_metrics')->selectRaw('path, avg(score) as avg_score, avg(blocking_scripts) as avg_block, avg(ldjson_count) as ld, avg(og_count) as og')
                ->where('created_at','>=',now()->subDays(2))->groupBy('path')->get();
            foreach($rows as $r){
                $advice=[]; if((int)$r->avg_block>0)$advice[]='Добавьте defer к скриптам';
                if((int)$r->ld===0)$advice[]='Добавьте JSON-LD';
                if((int)$r->og<3)$advice[]='Расширьте OG-теги';
                if((float)$r->avg_score<80)$advice[]='Уменьшите HTML/изображения';
                $issues[]=['path'=>$r->path,'avg_score'=>round((float)$r->avg_score,2),'hints'=>$advice];
            }
        } elseif (Storage::exists('metrics/ssr.jsonl')) {
            $lines = explode("\n", Storage::get('metrics/ssr.jsonl'));
            $agg = [];
            foreach ($lines as $ln) {
                $ln = trim($ln); if ($ln==='') continue;
                $j = json_decode($ln, true); if (!$j) continue;
                $p = $j['path']; $agg[$p] = $agg[$p] ?? ['sum'=>0,'n'=>0,'block'=>0,'ld'=>0,'og'=>0];
                $agg[$p]['sum'] += (int)($j['score'] ?? 0);
                $agg[$p]['n']   += 1;
                $agg[$p]['block'] += (int)($j['blocking'] ?? 0);
                $agg[$p]['ld'] += (int)($j['ld'] ?? 0);
                $agg[$p]['og'] += (int)($j['og'] ?? 0);
            }
            foreach ($agg as $p=>$a) {
                $avg = $a['n'] ? $a['sum']/$a['n'] : 0;
                $advice=[];
                if ($a['block']>0) $advice[]='Добавьте defer к скриптам';
                if ($a['ld']==0) $advice[]='Нет JSON-LD';
                if ($a['og']<3) $advice[]='Добавьте OG-теги';
                $issues[]=['path'=>$p,'avg_score'=>round($avg,2),'hints'=>$advice];
            }
        }
        usort($issues, fn($a,$b)=>$a['avg_score']<=>$b['avg_score']);
        return response()->json(['issues'=>$issues]);
    }
}
