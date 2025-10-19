<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Panel;
use Filament\PanelProvider;

final class AnalyticsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('analytics')
            ->path('analytics')
            ->brandName('Analytics')
            ->navigationGroups([
                'Catalog',
                'Telemetry',
                'Analytics',
                'Administration',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->sidebarCollapsibleOnDesktop()
            ->widgets([
                \App\Filament\Widgets\AnalyticsTabsWidget::class,
            ]);

        $plugins = array_values(array_filter([
            $this->instantiatePlugin('TomatoPHP\\FilamentTranslations\\FilamentTranslationsPlugin'),
            $this->instantiatePlugin('TomatoPHP\\FilamentLanguageSwitcher\\FilamentLanguageSwitcherPlugin'),
            $this->instantiatePlugin('RalphJSmit\\Filament\\Seo\\FilamentSeoPlugin'),
            $this->instantiatePlugin('TomatoPHP\\FilamentPayments\\FilamentPaymentsPlugin'),
        ]));

        if ($plugins !== []) {
            $panel = $panel->plugins($plugins);
        }

        $pages = array_values(array_filter([
            'App\\Filament\\Pages\\Analytics\\CtrPage',
            'App\\Filament\\Pages\\Analytics\\TrendsPage',
            'App\\Filament\\Pages\\Analytics\\TrendsAdvancedPage',
            'App\\Filament\\Pages\\Analytics\\QueuePage',
        ], static fn (string $class): bool => class_exists($class)));

        if ($pages !== []) {
            $panel = $panel->pages($pages);
        }

        return $panel;
    }

    private function instantiatePlugin(string $class): ?object
    {
        if (! class_exists($class) || ! method_exists($class, 'make')) {
            return null;
        }

        /** @var object $instance */
        $instance = $class::make();

        return $instance;
    }
}
