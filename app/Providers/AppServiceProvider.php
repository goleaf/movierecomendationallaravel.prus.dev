<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Movie;
use App\Models\User;
use App\Observers\MovieObserver;
use App\Services\Ingestion\IdempotencyService;
use App\Support\ImageProxyStorage;
use Filament\Facades\Filament;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
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
        $this->app->singleton(IdempotencyService::class, static fn (): IdempotencyService => new IdempotencyService);

        $this->app->singleton(ImageProxyStorage::class, static function (): ImageProxyStorage {
            $diskName = config('image-proxy.disk', config('filesystems.default'));
            $filesystem = Storage::disk($diskName);
            $directory = trim((string) config('image-proxy.directory', 'image-proxy'), '/');
            $ttl = (int) config('image-proxy.ttl', 86400);

            return new ImageProxyStorage($filesystem, $directory, $ttl);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('contact-submissions', function (Request $request): Limit {
            return Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

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
