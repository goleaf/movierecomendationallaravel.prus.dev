<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\SsrScoreWidget;
use App\Filament\Widgets\SsrStatsWidget;
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
        config()->set('ssrmetrics.storage.fallback.disk', 'local');
        config()->set('ssrmetrics.storage.fallback.files.incoming', 'metrics/ssr.jsonl');

        $fallbackDisk = config('ssrmetrics.storage.fallback.disk');
        $fallbackFile = config('ssrmetrics.storage.fallback.files.incoming');

        Storage::fake($fallbackDisk);

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

        Storage::disk($fallbackDisk)->put($fallbackFile, implode("\n", $lines));

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
    }
}
