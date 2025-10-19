<?php

namespace App\Filament\Pages\Analytics;

use App\Services\Analytics\CtrAnalyticsService;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;

class CtrPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.analytics.ctr';
    protected static ?string $navigationLabel = 'CTR';
    protected static ?string $navigationGroup = 'Analytics';
    protected static ?string $slug = 'ctr';

    public string $from;
    public string $to;
    public string $placement = '';
    public string $variant = '';

    /** @var array<int, array<string, mixed>> */
    public array $summary = [];

    /** @var array<string, int> */
    public array $placementClicks = [];

    /** @var array<int, array<string, mixed>> */
    public array $funnels = [];

    public ?string $lineSvg = null;
    public ?string $barsSvg = null;

    /** @var array<int, string> */
    public array $placementOptions = ['' => ''];

    /** @var array<int, string> */
    public array $variantOptions = ['' => ''];

    public function mount(): void
    {
        $this->from = now()->subDays(7)->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
        $this->placementOptions = [
            '' => __('admin.ctr.filters.placements.all'),
            'home' => __('admin.ctr.filters.placements.home'),
            'show' => __('admin.ctr.filters.placements.show'),
            'trends' => __('admin.ctr.filters.placements.trends'),
        ];
        $this->variantOptions = [
            '' => __('admin.ctr.filters.variants.all'),
            'A' => __('admin.ctr.filters.variants.a'),
            'B' => __('admin.ctr.filters.variants.b'),
        ];

        $this->refreshData();
    }

    public function refreshData(): void
    {
        $fromDate = $this->parseDate($this->from, now()->subDays(7)->format('Y-m-d'));
        $toDate = $this->parseDate($this->to, now()->format('Y-m-d'));

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $service = app(CtrAnalyticsService::class);

        $summary = $service->variantSummary($fromDate, $toDate, $this->placement ?: null, $this->variant ?: null);
        $this->summary = $summary['summary'];
        $this->placementClicks = $summary['placementClicks'];
        $this->funnels = $service->funnels($fromDate, $toDate);
        $this->lineSvg = $service->buildDailyCtrSvg($fromDate, $toDate);
        $this->barsSvg = $service->buildPlacementCtrSvg($fromDate, $toDate);

        $this->from = $fromDate->format('Y-m-d');
        $this->to = $toDate->format('Y-m-d');
    }

    public function updated($property): void
    {
        if (in_array($property, ['from', 'to', 'placement', 'variant'], true)) {
            $this->refreshData();
        }
    }

    private function parseDate(?string $value, string $fallback): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value ?? $fallback);
        } catch (\Throwable) {
            return CarbonImmutable::parse($fallback);
        }
    }
}
