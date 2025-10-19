<?php

namespace App\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use TomatoPHP\FilamentSubscriptions\Filament\Pages\Billing;
use TomatoPHP\FilamentSubscriptions\FilamentSubscriptionsPlugin;
use TomatoPHP\FilamentSubscriptions\FilamentSubscriptionsProvider;

class AnalyticsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('analytics')
            ->path('analytics')
            ->brandName('Analytics')
            ->plugins([
                FilamentLanguageSwitcherPlugin::make(),
            ])
            ->navigationGroups([
                'Catalog',
                'Telemetry',
                'Analytics',
                'Administration',
            ])
            ->plugin(FilamentSubscriptionsPlugin::make())
            ->tenantBillingProvider(new FilamentSubscriptionsProvider)
            ->requiresTenantSubscription()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->sidebarCollapsibleOnDesktop()
            ->plugin(
                FilamentSeoPlugin::make(),
            )
            ->widgets([
                \App\Filament\Widgets\AnalyticsTabsWidget::class,
            ])
            ->plugin(FilamentPaymentsPlugin::make())
            ->pages([
                Billing::class,
                \App\Filament\Pages\Analytics\CtrPage::class,
                \App\Filament\Pages\Analytics\TrendsPage::class,
                \App\Filament\Pages\Analytics\TrendsAdvancedPage::class,
                \App\Filament\Pages\Analytics\QueuePage::class,
            ]);
    }
}
