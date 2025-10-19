<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class CtrPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.analytics.ctr';

    protected static ?string $navigationLabel = 'CTR';

    protected static ?string $navigationGroup = 'Analytics';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $placement = '';

    #[Url]
    public string $variant = '';

    /**
     * @var array<int, array{variant:string, impressions:int, clicks:int, ctr:float}>
     */
    public array $summary = [];

    /**
     * @var array<int, array{placement:string, clicks:int}>
     */
    public array $placements = [];

    /**
     * @var array<int, array{label:string, impressions:int, clicks:int, views:int, ctr:float}>
     */
    public array $funnels = [];

    public function mount(): void
    {
        $this->from = $this->from !== '' ? $this->from : now()->copy()->subDays(7)->toDateString();
        $this->to = $this->to !== '' ? $this->to : now()->toDateString();

        $this->sanitizeFilters();
        $this->loadReport();
    }

    public function updatedFrom(): void
    {
        if ($this->synchronizeDates('from')) {
            return;
        }

        $this->loadReport();
    }

    public function updatedTo(): void
    {
        if ($this->synchronizeDates('to')) {
            return;
        }

        $this->loadReport();
    }

    public function updatedPlacement(): void
    {
        $sanitized = $this->sanitizePlacement($this->placement);
        if ($sanitized !== $this->placement) {
            $this->placement = $sanitized;

            return;
        }

        $this->loadReport();
    }

    public function updatedVariant(): void
    {
        $sanitized = $this->sanitizeVariant($this->variant);
        if ($sanitized !== $this->variant) {
            $this->variant = $sanitized;

            return;
        }

        $this->loadReport();
    }

    public function refreshReport(): void
    {
        $this->sanitizeFilters();
        $this->loadReport();
    }

    protected function sanitizeFilters(): void
    {
        if (! $this->isValidDate($this->from)) {
            $this->from = now()->copy()->subDays(7)->toDateString();
        }

        if (! $this->isValidDate($this->to)) {
            $this->to = now()->toDateString();
        }

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();

        if ($from->gt($to)) {
            $this->from = $to->copy()->subDays(7)->toDateString();
            $from = Carbon::parse($this->from)->startOfDay();
        }

        $this->placement = $this->sanitizePlacement($this->placement);
        $this->variant = $this->sanitizeVariant($this->variant);
    }

    protected function sanitizePlacement(string $placement): string
    {
        $placement = trim($placement);
        $allowed = ['', 'home', 'show', 'trends'];

        return in_array($placement, $allowed, true) ? $placement : '';
    }

    protected function sanitizeVariant(string $variant): string
    {
        $variant = strtoupper(trim($variant));
        $allowed = ['', 'A', 'B'];

        return in_array($variant, $allowed, true) ? $variant : '';
    }

    protected function synchronizeDates(string $field): bool
    {
        $value = $field === 'from' ? $this->from : $this->to;

        if (! $this->isValidDate($value)) {
            if ($field === 'from') {
                $this->from = now()->copy()->subDays(7)->toDateString();
            } else {
                $this->to = now()->toDateString();
            }

            return true;
        }

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();

        if ($from->gt($to)) {
            if ($field === 'from') {
                $this->from = $to->copy()->subDays(7)->toDateString();
            } else {
                $this->to = $from->copy()->addDays(7)->toDateString();
            }

            return true;
        }

        return false;
    }

    protected function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return Carbon::hasFormat($value, 'Y-m-d');
    }

    protected function loadReport(): void
    {
        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();

        $logs = DB::table('rec_ab_logs')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()]);
        $clicks = DB::table('rec_clicks')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()]);

        if ($this->placement !== '') {
            $clicks->where('placement', $this->placement);
        }

        if ($this->variant !== '') {
            $logs->where('variant', $this->variant);
            $clicks->where('variant', $this->variant);
        }

        $impVariant = $logs
            ->select('variant', DB::raw('count(*) as imps'))
            ->groupBy('variant')
            ->pluck('imps', 'variant')
            ->all();

        $clkVariant = $clicks
            ->select('variant', DB::raw('count(*) as clks'))
            ->groupBy('variant')
            ->pluck('clks', 'variant')
            ->all();

        $this->summary = collect(['A', 'B'])
            ->map(function (string $variant) use ($impVariant, $clkVariant) {
                $impressions = (int) ($impVariant[$variant] ?? 0);
                $clicks = (int) ($clkVariant[$variant] ?? 0);

                return [
                    'variant' => $variant,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'ctr' => $impressions > 0 ? round(100 * $clicks / $impressions, 2) : 0.0,
                ];
            })
            ->all();

        $clicksByPlacement = DB::table('rec_clicks')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()]);

        if ($this->variant !== '') {
            $clicksByPlacement->where('variant', $this->variant);
        }

        if ($this->placement !== '') {
            $clicksByPlacement->where('placement', $this->placement);
        }

        $this->placements = $clicksByPlacement
            ->select('placement', DB::raw('count(*) as clks'))
            ->groupBy('placement')
            ->pluck('clks', 'placement')
            ->map(fn ($count, $placement) => [
                'placement' => $placement,
                'clicks' => (int) $count,
            ])
            ->values()
            ->all();

        $totalImpressions = array_sum($impVariant);
        $totalClicks = array_sum($clkVariant);

        $totalViews = (int) DB::table('device_history')
            ->whereBetween('viewed_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->count();

        $placements = ['home', 'show', 'trends'];

        $funnels = [];
        foreach ($placements as $placement) {
            $clickCount = (int) DB::table('rec_clicks')
                ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
                ->where('placement', $placement)
                ->count();

            $funnels[] = [
                'label' => $placement,
                'impressions' => $totalImpressions,
                'clicks' => $clickCount,
                'views' => $totalViews,
                'ctr' => $totalImpressions > 0 ? round(100 * $clickCount / max(1, $totalImpressions), 2) : 0.0,
            ];
        }

        $funnels[] = [
            'label' => 'Итого',
            'impressions' => $totalImpressions,
            'clicks' => $totalClicks,
            'views' => $totalViews,
            'ctr' => $totalImpressions > 0 ? round(100 * $totalClicks / max(1, $totalImpressions), 2) : 0.0,
        ];

        $this->funnels = $funnels;
    }
}
