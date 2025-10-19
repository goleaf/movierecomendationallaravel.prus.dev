<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\SsrMetric;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

class SsrDropWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('analytics.widgets.ssr_drop.heading');
    }

    protected function getTableRecordKeyName(): ?string
    {
        return 'path';
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }

    protected function getDefaultTableRecordsPerPage(): int
    {
        return 10;
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50];
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return null;
        }

        $now = now();
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();
        $start = $now->copy()->subDay()->startOfDay();
        $end = $now->copy()->endOfDay();

        return SsrMetric::query()
            ->selectRaw(
                'path,
                avg(case when date(created_at) = ? then score end) as score_today,
                avg(case when date(created_at) = ? then score end) as score_yesterday,
                coalesce(avg(case when date(created_at) = ? then score end), 0) - coalesce(avg(case when date(created_at) = ? then score end), 0) as delta',
                [$today, $yesterday, $today, $yesterday]
            )
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('path')
            ->havingRaw(
                'coalesce(avg(case when date(created_at) = ? then score end), 0) - coalesce(avg(case when date(created_at) = ? then score end), 0) < 0',
                [$today, $yesterday, $today, $yesterday]
            )
            ->orderBy('delta');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('path')
                ->label(__('analytics.widgets.ssr_drop.columns.path'))
                ->wrap()
                ->sortable(),
            Tables\Columns\TextColumn::make('score_yesterday')
                ->label(__('analytics.widgets.ssr_drop.columns.yesterday'))
                ->numeric()
                ->formatStateUsing(static fn ($state): string => number_format((float) $state, 2))
                ->sortable(),
            Tables\Columns\TextColumn::make('score_today')
                ->label(__('analytics.widgets.ssr_drop.columns.today'))
                ->numeric()
                ->formatStateUsing(static fn ($state): string => number_format((float) $state, 2))
                ->sortable(),
            Tables\Columns\TextColumn::make('delta')
                ->label(__('analytics.widgets.ssr_drop.columns.delta'))
                ->numeric()
                ->formatStateUsing(static fn ($state): string => number_format((float) $state, 2))
                ->color(static fn ($state): string => $state >= 0 ? 'success' : 'danger')
                ->sortable(),
        ];
    }
}
