<?php

declare(strict_types=1);

namespace App\Filament\Pages\Analytics;

use App\Services\Analytics\SsrMetricsAggregator;
use Filament\Pages\Page;

class SsrPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.analytics.ssr';

    protected static ?string $navigationLabel = 'SSR';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $slug = 'ssr';

    /** @var array<string, mixed> */
    public array $summary = [
        'label' => '',
        'description' => '',
        'paths' => 0,
        'samples' => 0,
        'periods' => [],
        'source' => 'none',
    ];

    /** @var array{datasets: array<int, array<string, mixed>>, labels: array<int, string>} */
    public array $trend = [
        'datasets' => [],
        'labels' => [],
    ];

    /** @var array<int, array{path: string, score_today: float, score_yesterday: float, delta: float}> */
    public array $drops = [];

    public function mount(): void
    {
        $aggregator = app(SsrMetricsAggregator::class);

        $this->summary = $aggregator->summary();
        $this->trend = $aggregator->trend();
        $this->drops = $aggregator->dropRows();
    }
}
