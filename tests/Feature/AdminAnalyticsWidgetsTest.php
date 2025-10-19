<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\FunnelWidget;
use App\Filament\Widgets\SsrDropWidget;
use App\Filament\Widgets\SsrScoreWidget;
use App\Filament\Widgets\SsrStatsWidget;
use App\Filament\Widgets\ZTestWidget;
use App\Services\Analytics\SsrMetricsService as AnalyticsSsrMetricsService;
use Database\Seeders\Testing\FixturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAnalyticsWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private string $originalLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalLocale = app()->getLocale();
        app()->setLocale('en');

        Carbon::setTestNow('2024-03-20 12:00:00');
        $this->seed(FixturesSeeder::class);
    }

    protected function tearDown(): void
    {
        app()->setLocale($this->originalLocale);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_funnel_widget_aggregates_seeded_metrics(): void
    {
        Livewire::test(FunnelWidget::class)
            ->assertViewHas('rows', function (array $rows): bool {
                $this->assertSame('home', strtolower($rows[0]['label']));
                $this->assertSame(8, $rows[0]['imps']);
                $this->assertSame(4, $rows[0]['clicks']);
                $this->assertSame(6, $rows[0]['views']);

                $totals = end($rows);
                $this->assertSame(__('admin.ctr.funnels.total'), $totals['label']);
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
            ->assertSee('3 paths');

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
            ->assertSee('/');

        $analytics = app(AnalyticsSsrMetricsService::class);

        $headline = $analytics->headline();
        $this->assertSame(94, $headline['score']);
        $this->assertSame(3, $headline['paths']);

        $trend = $analytics->trend(2);
        $this->assertSame([
            Carbon::now()->subDay()->toDateString(),
            Carbon::now()->toDateString(),
        ], $trend['labels']);
        $this->assertEqualsWithDelta(93.0, $trend['datasets'][0]['data'][0], 0.01);
        $this->assertEqualsWithDelta(91.33, $trend['datasets'][0]['data'][1], 0.01);

        $dropRows = $analytics->dropRows();
        $this->assertNotEmpty($dropRows);
        $this->assertSame('/', $dropRows[0]['path']);
        $this->assertEqualsWithDelta(-8.0, $dropRows[0]['delta'], 0.01);
    }

    public function test_z_test_widget_displays_variant_breakdown(): void
    {
        $expectedImpressionsA = __('analytics.widgets.z_test.impressions', ['count' => number_format(9)]);
        $expectedClicksA = __('analytics.widgets.z_test.clicks', ['count' => number_format(7)]);
        $expectedDescriptionA = __('analytics.widgets.z_test.description_format', [
            'impressions' => $expectedImpressionsA,
            'clicks' => $expectedClicksA,
        ]);

        $expectedImpressionsB = __('analytics.widgets.z_test.impressions', ['count' => number_format(8)]);
        $expectedClicksB = __('analytics.widgets.z_test.clicks', ['count' => number_format(4)]);
        $expectedDescriptionB = __('analytics.widgets.z_test.description_format', [
            'impressions' => $expectedImpressionsB,
            'clicks' => $expectedClicksB,
        ]);

        Livewire::test(ZTestWidget::class)
            ->assertSee(__('analytics.widgets.z_test.ctr_a'))
            ->assertSee($expectedDescriptionA)
            ->assertSee(__('analytics.widgets.z_test.ctr_b'))
            ->assertSee($expectedDescriptionB)
            ->assertSee(__('analytics.widgets.z_test.z_test'));
    }
}
