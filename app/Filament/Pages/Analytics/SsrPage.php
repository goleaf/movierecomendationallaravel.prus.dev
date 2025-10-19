<?php

declare(strict_types=1);

namespace App\Filament\Pages\Analytics;

use App\Services\Analytics\SsrMetricsService;
use Filament\Pages\Page;

class SsrPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.analytics.ssr';

    protected static ?string $navigationLabel = 'SSR';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $slug = 'ssr';

    /** @var array{label: string, score: int, paths: int, description: string} */
    public array $headline = [
        'label' => '',
        'score' => 0,
        'paths' => 0,
        'description' => '',
    ];

    /** @var array{datasets: array<int, array{label: string, data: array<int, float>}>, labels: array<int, string>} */
    public array $trend = [
        'datasets' => [],
        'labels' => [],
    ];

    /** @var array<int, array{path: string, score_today: float, score_yesterday: float, delta: float}> */
    public array $drops = [];

    public function mount(): void
    {
        $service = app(SsrMetricsService::class);

        $this->headline = $service->headline();
        $this->trend = $service->trend();
        $this->drops = $service->dropRows();
    }
}
