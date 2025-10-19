<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SsrScoreWidget extends ChartWidget
{
    protected static ?string $heading='SSR Score (trend)';
    protected function getType(): string { return 'line'; }

    protected function getData(): array
    {
        $labels=[]; $series=[];
        if(\Schema::hasTable('ssr_metrics')){
            $rows=DB::table('ssr_metrics')->selectRaw('date(created_at) d, avg(score) s')->groupBy('d')->orderBy('d')->limit(30)->get();
            foreach($rows as $r){ $labels[]=$r->d; $series[]=round((float)$r->s,2); }
        } elseif (Storage::exists('metrics/last.json')){
            $json=json_decode(Storage::get('metrics/last.json'),true) ?: [];
            $labels[]=now()->toDateString(); $avg=0; $n=0; foreach($json as $row){ $avg+=(int)($row['score']??0); $n++; } $series[]=$n?round($avg/$n,2):0;
        }
        return ['datasets'=>[['label'=>'SSR score','data'=>$series]], 'labels'=>$labels];
    }
}
