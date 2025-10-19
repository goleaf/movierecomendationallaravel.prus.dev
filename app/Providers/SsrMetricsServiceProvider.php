<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SsrMetricsService;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewView;

class SsrMetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SsrMetricsService::class, function ($app): SsrMetricsService {
            return new SsrMetricsService(
                $app->make(FilesystemFactory::class),
                $app->make(Repository::class),
            );
        });
    }

    public function boot(): void
    {
        View::composer('filament.analytics.ssr-overview', function (ViewView $view): void {
            /** @var SsrMetricsService $metrics */
            $metrics = $this->app->make(SsrMetricsService::class);
            $data = $view->getData();

            $view->with('summary', $data['summary'] ?? $metrics->latestSummary());
            $view->with('trend', $data['trend'] ?? $metrics->scoreTrend(14));
        });
    }
}
