<?php

namespace App\Filament\Widgets;

use App\Models\SsrMetric;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SsrDropWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('analytics.widgets.ssr_drop.heading');
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return null;
        }

        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();

        $sql = <<<'SQL'
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
            select row_number() over (order by coalesce(score_today, 0) - coalesce(score_yesterday, 0), path) as id,
                path,
                coalesce(score_today, 0) as score_today,
                coalesce(score_yesterday, 0) as score_yesterday,
                coalesce(score_today, 0) - coalesce(score_yesterday, 0) as delta
            from pivot
            order by delta asc
            limit 10
        SQL;

        return SsrMetric::query()
            ->fromRaw("({$sql}) as ssr_metrics", [$yesterday, $today, $today, $yesterday]);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('path')
                ->label(__('analytics.widgets.ssr_drop.columns.path'))
                ->wrap(),
            Tables\Columns\TextColumn::make('score_yesterday')
                ->label(__('analytics.widgets.ssr_drop.columns.yesterday'))
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
            Tables\Columns\TextColumn::make('score_today')
                ->label(__('analytics.widgets.ssr_drop.columns.today'))
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
            Tables\Columns\TextColumn::make('delta')
                ->label(__('analytics.widgets.ssr_drop.columns.delta'))
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
        ];
    }
}
