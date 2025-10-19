<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Services\Analytics\CtrAnalyticsService;
use App\Support\AnalyticsFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CtrOverview extends Component
{
    public array $filters = [];

    /** @var array<int, array{variant: string, impressions: int, clicks: int, ctr: float}> */
    public array $summary = [];

    /** @var array<string, int> */
    public array $clicksByPlacement = [];

    /** @var array<string, array{imps: int, clks: int, views: int, ctr: float, cuped_ctr: float, view_rate: float}> */
    public array $funnels = [];

    /** @var array{impressions: int, clicks: int, views: int} */
    public array $totals = ['impressions' => 0, 'clicks' => 0, 'views' => 0];

    /** @var array{from: string, to: string} */
    public array $period = ['from' => '', 'to' => ''];

    /** @var array<int, string> */
    public array $variants = [];

    /** @var array<int, string> */
    public array $placements = [];

    protected CtrAnalyticsService $service;

    public function boot(CtrAnalyticsService $service): void
    {
        $this->service = $service;
    }

    public function mount(): void
    {
        $this->filters = [
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'placement' => '',
            'variant' => '',
        ];

        $this->refreshMetrics();
    }

    public function apply(): void
    {
        $this->validate();
        $this->refreshMetrics();
    }

    public function updatedFiltersTo(): void
    {
        if ($this->filters['to'] < $this->filters['from']) {
            $this->filters['to'] = $this->filters['from'];
        }
    }

    protected function rules(): array
    {
        $placementOptions = $this->placements === []
            ? AnalyticsFilters::placementCodes()
            : $this->placements;
        $variantOptions = $this->variants === []
            ? AnalyticsFilters::variantCodes()
            : $this->variants;

        return [
            'filters.from' => ['required', 'date'],
            'filters.to' => ['required', 'date', 'after_or_equal:filters.from'],
            'filters.placement' => ['nullable', 'string', Rule::in($placementOptions)],
            'filters.variant' => ['nullable', 'string', Rule::in($variantOptions)],
        ];
    }

    protected function refreshMetrics(): void
    {
        $metrics = $this->service->getMetrics(
            $this->filters['from'],
            $this->filters['to'],
            $this->filters['placement'] ?: null,
            $this->filters['variant'] ?: null,
        );

        $this->summary = $metrics['summary'];
        $this->clicksByPlacement = $metrics['clicksByPlacement'];
        $this->funnels = $metrics['funnels'];
        $this->totals = $metrics['totals'];
        $this->period = $metrics['period'];
        $this->variants = $metrics['variants'];
        $this->placements = $metrics['placements'];
    }

    public function render(): View
    {
        return view('livewire.analytics.ctr-overview');
    }
}
