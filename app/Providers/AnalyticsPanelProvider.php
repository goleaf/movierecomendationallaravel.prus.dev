<?php

namespace App\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use TomatoPHP\FilamentAccounts\FilamentAccountsPlugin;

class AnalyticsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('analytics')
            ->path('analytics')
            ->brandName('Analytics')
            ->navigationGroups([
                'Catalog',
                'Telemetry',
                'Analytics',
            ])
            ->plugin(
                FilamentAccountsPlugin::make()
                    ->useTypes()
                    ->useAvatar()
                    ->showAddressField()
                    ->showTypeField()
                    ->useExport()
                    ->useImport()
                    ->useLoginBy()
                    ->canLogin()
                    ->canBlocked()
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->sidebarCollapsibleOnDesktop()
            ->widgets([
                \App\Filament\Widgets\QueueStatsWidget::class,
                \App\Filament\Widgets\FunnelWidget::class,
                \App\Filament\Widgets\CtrLineWidget::class,
                \App\Filament\Widgets\CtrBarsWidget::class,
                \App\Filament\Widgets\ZTestWidget::class,
                \App\Filament\Widgets\SsrStatsWidget::class,
                \App\Filament\Widgets\SsrScoreWidget::class,
                \App\Filament\Widgets\SsrDropWidget::class,
            ])
            ->pages([
                \App\Filament\Pages\Analytics\CtrPage::class,
                \App\Filament\Pages\Analytics\TrendsPage::class,
                \App\Filament\Pages\Analytics\TrendsAdvancedPage::class,
                \App\Filament\Pages\Analytics\QueuePage::class,
            ]);
    }
}
