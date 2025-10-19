<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\FunnelWidget;
use App\Filament\Widgets\SsrDropWidget;
use App\Filament\Widgets\SsrScoreWidget;
use App\Filament\Widgets\SsrStatsWidget;
use App\Filament\Widgets\ZTestWidget;
use App\Services\Analytics\SsrAnalyticsService;
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
                $rowsCollection = collect($rows);

                $homeLabel = __('admin.ctr.filters.placements.home');
                $homeRow = $rowsCollection->first(fn (array $row): bool => in_array($row['label'], [$homeLabel, 'home', 'Home'], true));

                $this->assertIsArray($homeRow);
                $this->assertSame(8, $homeRow['imps']);
                $this->assertSame(4, $homeRow['clicks']);
                $this->assertSame(6, $homeRow['views']);

                $totalLabel = __('admin.ctr.funnels.total');
                $totalRow = $rowsCollection->first(fn (array $row): bool => in_array($row['label'], [$totalLabel, 'Итого', 'Total', 'admin.ctr.funnels.total'], true));

                $this->assertIsArray($totalRow);
                $this->assertSame(17, $totalRow['imps']);
                $this->assertSame(11, $totalRow['clicks']);
                $this->assertSame(12, $totalRow['views']);

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
            ->assertSee('/');

        $recent = collect(app(SsrAnalyticsService::class)->recent(5));

        $this->assertEqualsCanonicalizing(
            [185, 244, 201, 176, 192],
            $recent->map(fn (array $record): int => $record['normalized']['first_byte_ms'])->all()
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
