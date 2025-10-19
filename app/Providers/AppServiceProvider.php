<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Filament\Facades\Filament;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manageHorizon', function (User $user): bool {
            $adminEmails = config('queue.horizon.admin_emails');

            if (! is_array($adminEmails) || $adminEmails === []) {
                return false;
            }

            $email = $user->email;

            if ($email === null || $email === '') {
                return false;
            }

            return in_array(strtolower($email), $adminEmails, true);
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
