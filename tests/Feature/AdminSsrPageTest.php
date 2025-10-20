<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\Testing\FixturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminSsrPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_displays_ssr_overview(): void
    {
        Carbon::setTestNow('2024-03-20 12:00:00');
        $this->seed(FixturesSeeder::class);

        $this->get(route('admin.ssr'))
            ->assertOk()
            ->assertSee('SSR Score')
            ->assertSee(__('analytics.widgets.ssr_drop.heading'))
            ->assertSee('/');

        Carbon::setTestNow();
    }
}
