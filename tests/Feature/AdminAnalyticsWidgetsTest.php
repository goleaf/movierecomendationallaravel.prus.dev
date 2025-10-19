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
            ->assertSee('SSR Score')
            ->assertSee('94')
            ->assertSee('1 path');

        $scoreComponent = Livewire::test(SsrScoreWidget::class);
        $scoreComponent->call('rendering');

        /** @var array<string, mixed> $chartData */
        $chartData = (function (): array {
            /** @phpstan-ignore-next-line */
            return $this->getCachedData();
        })->call($scoreComponent->instance());

        $this->assertSame(['SSR score'], [$chartData['datasets'][0]['label']]);
        $this->assertSame([
            Carbon::now()->subDay()->toDateString(),
            Carbon::now()->toDateString(),
        ], $chartData['labels']);
        $this->assertEqualsWithDelta(93.0, $chartData['datasets'][0]['data'][0], 0.01);
        $this->assertEqualsWithDelta(91.33, $chartData['datasets'][0]['data'][1], 0.01);

        $scoreComponent->assertSee('SSR Score (trend)');

        Livewire::test(SsrDropWidget::class)
            ->assertSee('Top pages by SSR score drop')
            ->assertSee('/');
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
