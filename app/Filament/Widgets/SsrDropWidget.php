<?php

namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Illuminate\Support\Facades\DB;

class SsrDropWidget extends BaseWidget
{
    protected static ?string $heading='Top SSR Score Drops (день к дню)';
    protected int|string|array $columnSpan='full';

    protected function getTableQuery()
    {
        if(!\Schema::hasTable('ssr_metrics')) return DB::table(DB::raw('(select 1 where 0) as x'));
        $y = now()->subDay()->toDateString(); $t=now()->toDateString();
        $sql = "with agg as (select path, date(created_at) d, avg(score) s from ssr_metrics where date(created_at) in (?,?) group by path,d),
                pivot as (select a.path, max(case when a.d=? then a.s end) s_today, max(case when a.d=? then a.s end) s_yesterday from agg a group by a.path)
                select path, coalesce(s_today,0) s_today, coalesce(s_yesterday,0) s_yesterday, (coalesce(s_today,0)-coalesce(s_yesterday,0)) delta
                from pivot order by delta asc limit 10";
        return DB::table(DB::raw(f"({sql}) as t")).setBindings([y,t,t,y])
