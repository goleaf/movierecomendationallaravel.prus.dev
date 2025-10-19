<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\FunnelWidget;
use App\Filament\Widgets\ZTestWidget;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Seeders\DemoContentSeeder;
use Tests\TestCase;

class AnalyticsWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse('2025-01-15 12:00:00'));

        $this->seed(DemoContentSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_funnel_widget_renders_seeded_metrics(): void
    {
        Livewire::test(FunnelWidget::class)
            ->assertSee('Placement')
            ->assertSee('CTR (CUPED) %')
            ->assertSeeTextInOrder(['home', 'show', 'trends', 'Итого'])
            ->assertSeeText('6') // total impressions
            ->assertSeeText('4') // total views
            ->assertSeeText('3'); // clicks for home placement

        $this->assertEquals(
            [210, 238, 192, 265],
            DB::table('ssr_metrics')->orderBy('id')->pluck('first_byte_ms')->all()
        );
    }

    public function test_z_test_widget_displays_ctr_for_both_variants(): void
    {
        Livewire::test(ZTestWidget::class)
            ->assertSee('CTR A')
            ->assertSee('CTR B')
            ->assertSeeText('Imps:4 Clicks:4')
            ->assertSeeText('Imps:2 Clicks:2')
            ->assertSee('Z-test')
            ->assertSee('0.00')
            ->assertSee('p ≥ 0.05');
    }
}
