<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\FunnelWidget;
use App\Filament\Widgets\SsrDropWidget;
use App\Filament\Widgets\SsrScoreWidget;
use App\Filament\Widgets\SsrStatsWidget;
use App\Filament\Widgets\ZTestWidget;
use App\Services\Analytics\SsrMetricsAggregator;
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
                $this->assertSame('home', $rows[0]['label']);
                $this->assertSame(17, $rows[0]['imps']);
                $this->assertSame(4, $rows[0]['clicks']);
                $this->assertSame(12, $rows[0]['views']);

                $totals = end($rows);
                $this->assertSame('Итого', $totals['label']);
                $this->assertSame(17, $totals['imps']);
                $this->assertSame(11, $totals['clicks']);
                $this->assertSame(12, $totals['views']);

                return true;
            });
    }

    public function test_ssr_widgets_render_seeded_scores(): void
    {
        Livewire::test(SsrStatsWidget::class)
            ->assertSee('Today')
            ->assertSee('Δ -1.67 vs yesterday')
            ->assertSee('First byte: 204.00 ms (+11.00 ms vs yesterday)')
            ->assertSee('Range: 2024-03-20 → 2024-03-20')
            ->assertSee('Yesterday')
            ->assertSee('Δ -0.33 vs prior day')
            ->assertSee('First byte: 193.00 ms (-8.67 ms vs prior day)')
            ->assertSee('Last 7 days')
            ->assertSee('Δ +3.83 vs previous week')
            ->assertSee('First byte: 200.38 ms (-10.63 ms vs previous week)');

        $aggregator = app(SsrMetricsAggregator::class);
        $summary = $aggregator->summary();
        $this->assertSame('Tracking 3 paths across 8 samples between 2024-03-14 and 2024-03-20.', $summary['description']);

        $scoreComponent = Livewire::test(SsrScoreWidget::class);
        $scoreComponent->call('rendering');

        /** @var array<string, mixed> $chartData */
        $chartData = (function (): array {
            /** @phpstan-ignore-next-line */
            return $this->getCachedData();
        })->call($scoreComponent->instance());

        $this->assertSame(['SSR score'], [$chartData['datasets'][0]['label']]);
        $this->assertSame([
            Carbon::now()->subDays(8)->toDateString(),
            Carbon::now()->subDays(2)->toDateString(),
            Carbon::now()->subDay()->toDateString(),
            Carbon::now()->toDateString(),
        ], $chartData['labels']);
        $this->assertEqualsWithDelta(89.0, $chartData['datasets'][0]['data'][0], 0.01);
        $this->assertEqualsWithDelta(93.33, $chartData['datasets'][0]['data'][1], 0.01);
        $this->assertEqualsWithDelta(93.0, $chartData['datasets'][0]['data'][2], 0.01);
        $this->assertEqualsWithDelta(91.33, $chartData['datasets'][0]['data'][3], 0.01);

        $scoreComponent->assertSee('SSR Score (trend)');

        Livewire::test(SsrDropWidget::class)
            ->assertSee('Top pages by SSR score drop')
            ->assertSee('/')
            ->assertSee('88.00')
            ->assertSee('96.00');

        $this->assertEquals(
            [185, 244, 201, 176, 192, 198, 207, 200, 215, 210, 208],
            DB::table('ssr_metrics')->orderBy('id')->pluck('first_byte_ms')->all()
        );
    }

    public function test_z_test_widget_displays_variant_breakdown(): void
    {
        Livewire::test(ZTestWidget::class)
            ->assertSee('CTR A')
            ->assertSee('Imps:9 Clicks:7')
            ->assertSee('CTR B')
            ->assertSee('Imps:8 Clicks:4')
            ->assertSee('Z-test');
    }
}
