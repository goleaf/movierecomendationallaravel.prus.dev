<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\SsrMetricsService;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class SsrDropWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('analytics.widgets.ssr_drop.heading');
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return app(SsrMetricsService::class)->dropQuery();
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('path')
                ->label(__('analytics.widgets.ssr_drop.columns.path'))
                ->wrap(),
            Tables\Columns\TextColumn::make('score_yesterday')
                ->label(__('analytics.widgets.ssr_drop.columns.yesterday'))
                ->formatStateUsing(static fn ($state) => number_format((float) $state, 2)),
            Tables\Columns\TextColumn::make('score_today')
                ->label(__('analytics.widgets.ssr_drop.columns.today'))
                ->formatStateUsing(static fn ($state) => number_format((float) $state, 2)),
            Tables\Columns\TextColumn::make('delta')
                ->label(__('analytics.widgets.ssr_drop.columns.delta'))
                ->formatStateUsing(static fn ($state) => number_format((float) $state, 2))
                ->color(static fn ($state) => $state >= 0 ? 'success' : 'danger'),
        ];
    }
}
