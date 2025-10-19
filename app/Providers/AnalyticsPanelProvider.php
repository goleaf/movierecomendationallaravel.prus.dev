<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use TomatoPHP\FilamentTranslations\FilamentTranslationsPlugin;

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
