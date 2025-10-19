<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\FunnelWidget;
use App\Filament\Widgets\SsrDropWidget;
use App\Filament\Widgets\SsrScoreWidget;
use App\Filament\Widgets\SsrStatsWidget;
use App\Filament\Widgets\ZTestWidget;
use Database\Seeders\Testing\FixturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAnalyticsWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2024-03-20 12:00:00');
        $this->seed(FixturesSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_funnel_widget_aggregates_seeded_metrics(): void
    {
        Livewire::test(FunnelWidget::class)
            ->assertViewHas('rows', function (array $rows): bool {
                $this->assertSame('Home', $rows[0]['label']);
                $this->assertSame(8, $rows[0]['imps']);
                $this->assertSame(4, $rows[0]['clicks']);
                $this->assertSame(0, $rows[0]['views']);

                $totals = end($rows);
                $this->assertSame('admin.ctr.funnels.total', $totals['label']);
                $this->assertSame(17, $totals['imps']);
                $this->assertSame(11, $totals['clicks']);
                $this->assertSame(0, $totals['views']);

                return true;
            });
    }

    public function test_ssr_widgets_render_seeded_scores(): void
    {
        Livewire::test(SsrStatsWidget::class)
            ->assertSee('Today')
            ->assertSee('91.33')
            ->assertSee('Δ score: -1.67')
            ->assertSee('Δ first byte: +11 ms')
            ->assertSee('Δ paths: +1')
            ->assertSee('Δ samples: +1')
            ->assertSee('Δ score: +92.00')
            ->assertSee('Δ first byte: +200 ms')
            ->assertSee('Δ paths: +3')
            ->assertSee('Δ samples: +5')
            ->assertSee('3 paths')
            ->assertSee('3 samples')
            ->assertSee('First byte: 204 ms')
            ->assertSee('Last 7 days');

        $scoreComponent = Livewire::test(SsrScoreWidget::class);
        $scoreComponent->call('rendering');

        /** @var array<string, mixed> $chartData */
        $chartData = (function (): array {
            /** @phpstan-ignore-next-line */
            return $this->getCachedData();
        })->call($scoreComponent->instance());

        $this->assertSame([
            __('analytics.widgets.ssr_score.datasets.daily'),
            __('analytics.widgets.ssr_score.datasets.rolling'),
        ], array_column($chartData['datasets'], 'label'));
        $this->assertSame([
            Carbon::now()->subDay()->toDateString(),
            Carbon::now()->toDateString(),
        ], $chartData['labels']);
        $this->assertEqualsWithDelta(93.0, $chartData['datasets'][0]['data'][0], 0.01);
        $this->assertEqualsWithDelta(91.33, $chartData['datasets'][0]['data'][1], 0.01);
        $this->assertEqualsWithDelta(93.0, $chartData['datasets'][1]['data'][0], 0.01);
        $this->assertEqualsWithDelta(92.0, $chartData['datasets'][1]['data'][1], 0.01);

        $scoreComponent->assertSee('SSR score trend (daily vs 7-day average)');

        Livewire::test(SsrDropWidget::class)
            ->assertSee('/');

        $this->assertEquals(
            [185, 244, 201, 176, 192],
            DB::table('ssr_metrics')->orderBy('id')->pluck('first_byte_ms')->all()
        );
    }

    public function test_z_test_widget_displays_variant_breakdown(): void
    {
        Livewire::test(ZTestWidget::class)
            ->assertSee('CTR A')
            ->assertSee('Imps: 9 · Clicks: 7')
            ->assertSee('CTR B')
            ->assertSee('Imps: 8 · Clicks: 4')
            ->assertSee('Z-test');
    }
}
