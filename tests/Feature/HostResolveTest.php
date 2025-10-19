<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ResolveHost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HostResolveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', ResolveHost::class])->get('/host-probe', function (Request $request) {
            return response()->json([
                'app_locale' => app()->getLocale(),
                'config_locale' => config('app.locale'),
                'storefront' => $request->attributes->get('storefront'),
                'config_storefront' => config('app.storefront'),
            ]);
        });
    }

    public function test_it_uses_default_settings_for_unknown_host(): void
    {
        config([
            'app.locale' => 'en',
            'app.storefront' => 'main',
            'hosts.default' => [
                'locale' => 'en',
                'storefront' => 'main',
            ],
            'hosts.domains' => [],
        ]);

        $response = $this->get('http://unknown.test/host-probe');

        $response->assertOk();
        $response->assertJson([
            'app_locale' => 'en',
            'config_locale' => 'en',
            'storefront' => 'main',
            'config_storefront' => 'main',
        ]);
    }

    public function test_it_resolves_exact_domain_configuration(): void
    {
        config([
            'app.locale' => 'en',
            'app.storefront' => 'main',
            'hosts.default' => [
                'locale' => 'en',
                'storefront' => 'main',
            ],
            'hosts.domains' => [
                'br.example.com' => [
                    'locale' => 'pt-BR',
                    'storefront' => 'br',
                ],
            ],
        ]);

        $response = $this->get('http://br.example.com/host-probe');

        $response->assertOk();
        $response->assertJson([
            'app_locale' => 'pt-BR',
            'config_locale' => 'pt-BR',
            'storefront' => 'br',
            'config_storefront' => 'br',
        ]);
    }

    public function test_it_supports_wildcard_subdomains(): void
    {
        config([
            'app.locale' => 'en',
            'app.storefront' => 'main',
            'hosts.default' => [
                'locale' => 'en',
                'storefront' => 'main',
            ],
            'hosts.domains' => [
                '*.fr.example.com' => [
                    'locale' => 'fr',
                    'storefront' => 'fr',
                ],
            ],
        ]);

        $response = $this->get('http://shop.fr.example.com/host-probe');

        $response->assertOk();
        $response->assertJson([
            'app_locale' => 'fr',
            'config_locale' => 'fr',
            'storefront' => 'fr',
            'config_storefront' => 'fr',
        ]);
    }
}
