<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\SsrScoreWidget;
use App\Filament\Widgets\SsrStatsWidget;
use App\Services\Analytics\SsrMetricsService as AnalyticsSsrMetricsService;
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
                'recorded_at' => '2024-03-18T10:00:00Z',
                'ts' => '2024-03-18T10:00:00Z',
                'path' => '/home',
                'score' => 80,
            ],
            [
                'recorded_at' => '2024-03-18T11:30:00Z',
                'ts' => '2024-03-18T11:30:00Z',
                'path' => '/about',
                'score' => 90,
            ],
            [
                'recorded_at' => '2024-03-19T09:15:00Z',
                'ts' => '2024-03-19T09:15:00Z',
                'path' => '/home',
                'score' => 70,
            ],
            [
                'recorded_at' => '2024-03-19T10:45:00Z',
                'ts' => '2024-03-19T10:45:00Z',
                'path' => '/contact',
                'score' => 95,
            ],
        ];

        $lines = array_map(static fn (array $record): string => json_encode($record, JSON_THROW_ON_ERROR), $records);

        Storage::disk('local')->put('metrics/ssr.jsonl', implode("\n", $lines));

        Livewire::test(SsrStatsWidget::class)
            ->assertSee('SSR Score')
            ->assertSee('84')
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
            '2024-03-18',
            '2024-03-19',
        ], $chartData['labels']);
        $this->assertEqualsWithDelta(85.0, $chartData['datasets'][0]['data'][0], 0.01);
        $this->assertEqualsWithDelta(82.5, $chartData['datasets'][0]['data'][1], 0.01);

        $analytics = app(AnalyticsSsrMetricsService::class);

        $headline = $analytics->headline();
        $this->assertSame(95, $headline['score']);
        $this->assertSame(3, $headline['paths']);

        $trend = $analytics->trend(2);
        $this->assertSame(['2024-03-18', '2024-03-19'], $trend['labels']);
        $this->assertEqualsWithDelta(85.0, $trend['datasets'][0]['data'][0], 0.01);
        $this->assertEqualsWithDelta(82.5, $trend['datasets'][0]['data'][1], 0.01);
    }
}
