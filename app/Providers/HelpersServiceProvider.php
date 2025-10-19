<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class HelpersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (glob(app_path('Support/helpers.php')) as $file) require_once $file;
    }
    public function boot(): void {}
}
