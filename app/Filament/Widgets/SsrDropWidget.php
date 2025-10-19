<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\SsrMetric;
use App\Services\Analytics\SsrMetricsService;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

class SsrDropWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('analytics.widgets.ssr_drop.heading');
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        /** @var SsrMetricsService $metrics */
        $metrics = app(SsrMetricsService::class);

        if (! $metrics->hasMetrics()) {
            return null;
        }

        $yesterday = Carbon::now()->subDay()->startOfDay();
        $today = Carbon::now()->startOfDay();

        $query = $metrics->dropComparisonQuery($yesterday, $today);

        if ($query === null) {
            return null;
        }

        return SsrMetric::query()->fromSub($query, 'drops');
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
