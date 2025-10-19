<?php

declare(strict_types=1);

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
        $timestampColumn = Schema::hasColumn('ssr_metrics', 'collected_at') ? 'collected_at' : 'created_at';

        return SsrMetric::query()
            ->fromSub(function ($query) use ($today, $timestampColumn, $yesterday) {
                $query
                    ->fromSub(function ($aggregateQuery) use ($today, $timestampColumn, $yesterday) {
                        $aggregateQuery
                            ->from('ssr_metrics')
                            ->selectRaw(sprintf('path, date(%s) as d, avg(score) as avg_score', $timestampColumn))
                            ->whereIn(DB::raw(sprintf('date(%s)', $timestampColumn)), [$yesterday, $today])
                            ->groupBy('path', 'd');
                    }, 'agg')
                    ->selectRaw(
                        'path,
                        max(case when d = ? then avg_score end) as score_today,
                        max(case when d = ? then avg_score end) as score_yesterday',
                        [$today, $yesterday]
                    )
                    ->groupBy('path');
            }, 'pivot')
            ->select([
                DB::raw('row_number() over (order by coalesce(score_today, 0) - coalesce(score_yesterday, 0), path) as id'),
                'path',
                DB::raw('coalesce(score_today, 0) as score_today'),
                DB::raw('coalesce(score_yesterday, 0) as score_yesterday'),
                DB::raw('coalesce(score_today, 0) - coalesce(score_yesterday, 0) as delta'),
            ])
            ->whereRaw('(coalesce(score_today, 0) - coalesce(score_yesterday, 0)) < 0')
            ->orderBy('delta');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('path')
                ->label(__('analytics.widgets.ssr_drop.columns.path'))
                ->wrap(),
            Tables\Columns\TextColumn::make('score_yesterday')
                ->label(__('analytics.widgets.ssr_drop.columns.yesterday'))
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                ->sortable(),
            Tables\Columns\TextColumn::make('score_today')
                ->label(__('analytics.widgets.ssr_drop.columns.today'))
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                ->sortable(),
            Tables\Columns\TextColumn::make('delta')
                ->label(__('analytics.widgets.ssr_drop.columns.delta'))
                ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                ->sortable(),
        ];
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'delta';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'asc';
    }

    protected function getTablePaginationPageOptions(): array
    {
        return [10, 25, 50];
    }
}
