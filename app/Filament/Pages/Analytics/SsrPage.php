<?php

declare(strict_types=1);

namespace App\Filament\Pages\Analytics;

use App\Services\Analytics\SsrAnalyticsService;
use Filament\Pages\Page;

class SsrPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.analytics.ssr';

    protected static ?string $navigationLabel = 'SSR';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $slug = 'ssr';

    /**
     * @var array{
     *     label: string,
     *     periods: array<string, array{score: float, first_byte_ms: float, samples: int, paths: int, delta?: array{score: float, first_byte_ms: float, samples: int, paths: int}, range?: array{from: string, to: string}}>
     * }
     */
    public array $headline = [
        'label' => '',
        'periods' => [],
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
        $service = app(SsrAnalyticsService::class);

        $this->headline = $service->headline();
        $this->trend = $service->trend();
        $this->drops = $service->dropRows();
    }
}
