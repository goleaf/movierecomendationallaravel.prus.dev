<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use SolutionForest\TabLayoutPlugin\Components\Tabs;
use SolutionForest\TabLayoutPlugin\Components\Tabs\Tab as TabLayoutTab;
use SolutionForest\TabLayoutPlugin\Schemas\Components\LivewireContainer;
use SolutionForest\TabLayoutPlugin\Widgets\TabsWidget as BaseTabsWidget;

class AnalyticsTabsWidget extends BaseTabsWidget
{
    protected static ?string $heading = null;

    public static function tabs(Tabs $tabs): Tabs
    {
        return $tabs
            ->id('analytics-overview-tabs')
            ->contained(true);
    }

    protected function getHeading(): ?string
    {
        return __('admin.analytics_tabs.heading');
    }

    protected function schema(): array
    {
        return [
            TabLayoutTab::make(__('admin.analytics_tabs.queue.label'), 'queue')
                ->icon('heroicon-o-queue-list')
                ->schema([
                    LivewireContainer::make(QueueStatsWidget::class),
                ]),
            TabLayoutTab::make(__('admin.analytics_tabs.ctr.label'), 'ctr')
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    LivewireContainer::make(CtrLineWidget::class),
                    LivewireContainer::make(CtrBarsWidget::class),
                ])
                ->columns(1),
            TabLayoutTab::make(__('admin.analytics_tabs.funnels.label'), 'funnels')
                ->icon('heroicon-o-funnel')
                ->schema([
                    LivewireContainer::make(FunnelWidget::class),
                ]),
            TabLayoutTab::make(__('admin.analytics_tabs.ssr.label'), 'ssr')
                ->icon('heroicon-o-sparkles')
                ->schema([
                    LivewireContainer::make(SsrStatsWidget::class),
                    LivewireContainer::make(SsrScoreWidget::class),
                    LivewireContainer::make(SsrDropWidget::class),
                ]),
            TabLayoutTab::make(__('admin.analytics_tabs.experiments.label'), 'experiments')
                ->icon('heroicon-o-beaker')
                ->schema([
                    LivewireContainer::make(ZTestWidget::class),
                ]),
        ];
    }
}
