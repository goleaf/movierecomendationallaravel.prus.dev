<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\SsrScoreWidget;
use App\Filament\Widgets\SsrStatsWidget;
use App\Services\Analytics\SsrMetricsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SsrAnalyticsFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_widgets_render_jsonl_fallback_when_table_missing(): void
    {
        Storage::fake('local');

        Schema::dropIfExists('ssr_metrics');

        $records = [
            [
                'ts' => '2024-03-18T10:00:00Z',
                'path' => '/home',
                'score' => 80,
            ],
            [
                'ts' => '2024-03-18T11:30:00Z',
                'path' => '/about',
                'score' => 90,
            ],
            [
                'ts' => '2024-03-19T09:15:00Z',
                'path' => '/home',
                'score' => 70,
            ],
            [
                'ts' => '2024-03-19T10:45:00Z',
                'path' => '/contact',
                'score' => 95,
            ],
        ];

        $lines = array_map(static fn (array $record): string => json_encode($record, JSON_THROW_ON_ERROR), $records);

        Storage::disk('local')->put('metrics/ssr.jsonl', implode("\n", $lines));

        Livewire::test(SsrStatsWidget::class)
            ->assertSee('Today')
            ->assertSee('Δ -2.50 vs yesterday')
            ->assertSee('First byte not recorded')
            ->assertSee('Range: 2024-03-19 → 2024-03-19')
            ->assertSee('Yesterday')
            ->assertSee('Δ n/a')
            ->assertSee('Last 7 days')
            ->assertSee('Δ n/a');

        $summary = app(SsrMetricsAggregator::class)->summary();
        $this->assertSame('Tracking 3 paths across 4 samples between 2024-03-13 and 2024-03-19.', $summary['description']);

        $scoreComponent = Livewire::test(SsrScoreWidget::class);
        $scoreComponent->call('rendering');

        /** @var array<string, mixed> $chartData */
        $chartData = (function (): array {
            /** @phpstan-ignore-next-line */
            return $this->getCachedData();
        })->call($scoreComponent->instance());

        $this->assertSame(['SSR score'], [$chartData['datasets'][0]['label']]);
        $this->assertSame([
            '2024-03-18',
            '2024-03-19',
        ], $chartData['labels']);
        $this->assertEqualsWithDelta(85.0, $chartData['datasets'][0]['data'][0], 0.01);
        $this->assertEqualsWithDelta(82.5, $chartData['datasets'][0]['data'][1], 0.01);
    }
}
