<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = $this->resolveSettings($request->getHost());

        $locale = Arr::get($settings, 'locale');
        if (is_string($locale) && $locale !== '') {
            $this->setLocale($locale);
        }

        $storefront = Arr::get($settings, 'storefront');
        if (is_string($storefront) && $storefront !== '') {
            $this->setStorefront($request, $storefront);
        }

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveSettings(string $host): array
    {
        $host = Str::lower($host);

        $domains = config('hosts.domains', []);
        if (is_array($domains)) {
            $exact = $domains[$host] ?? null;
            if (is_array($exact)) {
                return $exact;
            }

            foreach ($domains as $pattern => $settings) {
                if (! is_string($pattern) || ! is_array($settings)) {
                    continue;
                }

                $pattern = Str::lower($pattern);

                if ($pattern === $host) {
                    return $settings;
                }

                if (! str_contains($pattern, '*')) {
                    continue;
                }

                $regex = '/^'.str_replace('\\*', '.*', preg_quote($pattern, '/')).'$/';
                if (preg_match($regex, $host) === 1) {
                    return $settings;
                }
            }
        }

        $default = config('hosts.default', []);
        if (! is_array($default)) {
            $default = [];
        }

        return array_filter($default) + [
            'locale' => config('app.locale'),
            'storefront' => config('app.storefront', 'default'),
        ];
    }

    protected function setLocale(string $locale): void
    {
        app()->setLocale($locale);
        config(['app.locale' => $locale]);
    }

    protected function setStorefront(Request $request, string $storefront): void
    {
        $request->attributes->set('storefront', $storefront);
        config(['app.storefront' => $storefront]);
    }
}
