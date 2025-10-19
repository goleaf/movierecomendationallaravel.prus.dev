<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SsrDropWidget extends BaseWidget
{
    protected static ?string $heading = 'Top SSR Score Drops (день к дню)';
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery()
    {
        if (!Schema::hasTable('ssr_metrics')) {
            return DB::table(DB::raw('(select 1) as empty'))->whereRaw('1=0');
        }

        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();

        $sql = <<<SQL
            with agg as (
                select path, date(created_at) as d, avg(score) as avg_score
                from ssr_metrics
                where date(created_at) in (?, ?)
                group by path, date(created_at)
            ), pivot as (
                select path,
                    max(case when d = ? then avg_score end) as score_today,
                    max(case when d = ? then avg_score end) as score_yesterday
                from agg
                group by path
            )
            select path,
                coalesce(score_today, 0) as score_today,
                coalesce(score_yesterday, 0) as score_yesterday,
                coalesce(score_today, 0) - coalesce(score_yesterday, 0) as delta
            from pivot
            order by delta asc
            limit 10
        SQL;

        return DB::table(DB::raw("({$sql}) as t"))
            ->setBindings([$yesterday, $today, $today, $yesterday]);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('path')->label('Path')->wrap(),
            Tables\Columns\TextColumn::make('score_yesterday')
                ->label('Вчера')
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
            Tables\Columns\TextColumn::make('score_today')
                ->label('Сегодня')
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
            Tables\Columns\TextColumn::make('delta')
                ->label('Δ')
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
        ];
    }
}
