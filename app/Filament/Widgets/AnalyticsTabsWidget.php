<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

final class AnalyticsTabsWidget extends Widget
{
    protected static string $view = 'filament.widgets.analytics-tabs';

    protected function getViewData(): array
    {
        return [
            'heading' => $this->getHeading(),
            'tabs' => $this->getTabs(),
            'widgetId' => $this->id,
        ];
    }

    private function getHeading(): ?string
    {
        return __('admin.analytics_tabs.heading');
    }

    /**
     * @return array<int, array{key: string, label: string, icon: string, widgets: array<int, class-string<Widget>>}>
     */
    private function getTabs(): array
    {
        return [
            [
                'key' => 'queue',
                'label' => __('admin.analytics_tabs.queue.label'),
                'icon' => 'heroicon-o-queue-list',
                'widgets' => [QueueStatsWidget::class],
            ],
            [
                'key' => 'ctr',
                'label' => __('admin.analytics_tabs.ctr.label'),
                'icon' => 'heroicon-o-chart-bar',
                'widgets' => [
                    CtrLineWidget::class,
                    CtrBarsWidget::class,
                ],
            ],
            [
                'key' => 'funnels',
                'label' => __('admin.analytics_tabs.funnels.label'),
                'icon' => 'heroicon-o-funnel',
                'widgets' => [FunnelWidget::class],
            ],
            [
                'key' => 'ssr',
                'label' => __('admin.analytics_tabs.ssr.label'),
                'icon' => 'heroicon-o-sparkles',
                'widgets' => [
                    SsrStatsWidget::class,
                    SsrScoreWidget::class,
                    SsrDropWidget::class,
                ],
            ],
            [
                'key' => 'experiments',
                'label' => __('admin.analytics_tabs.experiments.label'),
                'icon' => 'heroicon-o-beaker',
                'widgets' => [ZTestWidget::class],
            ],
        ];
    }
}
