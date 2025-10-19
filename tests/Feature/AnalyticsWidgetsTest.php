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

        config()->set('cache.stores.redis', [
            'driver' => 'array',
        ]);

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
            ->assertSeeTextInOrder(['Home', 'Show', 'Trends', 'admin.ctr.funnels.total'])
            ->assertSeeText('6') // total impressions
            ->assertSeeText('0') // total views
            ->assertSeeText('3'); // clicks for home placement

        $this->assertEquals(
            [210, 238, 192, 265],
            DB::table('ssr_metrics')->orderBy('id')->pluck('first_byte_ms')->all()
        );

        $this->assertEquals(
            [480000, 512000, 420000, 550000],
            DB::table('ssr_metrics')->orderBy('id')->pluck('html_bytes')->all()
        );
    }

    public function test_z_test_widget_displays_ctr_for_both_variants(): void
    {
        $component = Livewire::test(ZTestWidget::class);

        $content = str_replace("\u{A0}", ' ', strip_tags($component->html()));

        $this->assertStringContainsString('CTR A', $content);
        $this->assertStringContainsString('CTR B', $content);
        $this->assertStringContainsString('Imps: 4', $content);
        $this->assertStringContainsString('Clicks: 4', $content);
        $this->assertStringContainsString('Imps: 2', $content);
        $this->assertStringContainsString('Clicks: 2', $content);
        $this->assertStringContainsString('Z-test', $content);
        $this->assertStringContainsString('Need at least 1,000 impressions per variant to evaluate significance.', $content);
    }
}
