<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Movie;
use App\Models\User;
use App\Observers\MovieObserver;
use App\Services\Analytics\CtrAnalyticsService;
use App\Services\Metrics\PrometheusMetricsService;
use App\Support\MetricsCache;
use Filament\Facades\Filament;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use TomatoPHP\FilamentSubscriptions\Facades\FilamentSubscriptions;
use TomatoPHP\FilamentSubscriptions\Services\Contracts\Subscriber;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MetricsCache::class, static fn (): MetricsCache => new MetricsCache);

        $this->app->singleton(PrometheusMetricsService::class, function (Application $app): PrometheusMetricsService {
            return new PrometheusMetricsService(
                $app->make(CtrAnalyticsService::class),
                $app->make(MetricsCache::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Movie::observe(MovieObserver::class);

        $horizonAdmins = array_values(array_unique(array_filter(
            array_map(
                static fn (string $email): string => mb_strtolower(trim($email)),
                config('queue.management.admins', []),
            ),
            static fn (string $email): bool => $email !== '',
        )));

        Gate::define('manageHorizonQueues', function (?User $user) use ($horizonAdmins): bool {
            if (! $user instanceof User) {
                return false;
            }

            if ($horizonAdmins === []) {
                return false;
            }

            return in_array(mb_strtolower($user->email), $horizonAdmins, true);
        });

        Filament::serving(function (): void {
            if (FilamentSubscriptions::getOptions()->doesntContain(
                fn (Subscriber $subscriber): bool => $subscriber->model === User::class
            )) {
                FilamentSubscriptions::register(
                    Subscriber::make('Users')
                        ->model(User::class)
                );
            }
        });
    }
}
