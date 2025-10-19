<?php

namespace App\Filament\Pages\Analytics;

use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;

class CtrPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.analytics.ctr';

    protected static ?string $navigationLabel = 'CTR';

    protected static ?string $navigationGroup = 'Analytics';

    #[Url(as: 'from')]
    public string $from;

    #[Url(as: 'to')]
    public string $to;

    #[Url(as: 'placement')]
    public string $placement = '';

    #[Url(as: 'variant')]
    public string $variant = '';

    /**
     * @var array<int,array{variant:string,imps:int,clks:int,ctr:float}>
     */
    public array $summary = [];

    /**
     * @var array<int,array{placement:string,clicks:int}>
     */
    public array $clicksByPlacement = [];

    /**
     * @var array<int,array{label:string,imps:int,clks:int,views:int}>
     */
    public array $funnels = [];

    /**
     * @var array<int,string>
     */
    public array $availablePlacements = [];

    public function mount(): void
    {
        $this->from = $this->from ?? now()->subDays(7)->toDateString();
        $this->to = $this->to ?? now()->toDateString();

        $this->loadData();
    }

    public function applyFilters(): void
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        if (! $this->tablesAvailable()) {
            $this->summary = [];
            $this->clicksByPlacement = [];
            $this->funnels = [];
            $this->availablePlacements = [];

            return;
        }

        $from = "{$this->from} 00:00:00";
        $to = "{$this->to} 23:59:59";

        $logsQuery = DB::table('rec_ab_logs')
            ->whereBetween('created_at', [$from, $to]);

        $clicksQuery = DB::table('rec_clicks')
            ->whereBetween('created_at', [$from, $to]);

        if ($this->placement !== '') {
            $clicksQuery->where('placement', $this->placement);
        }

        if ($this->variant !== '') {
            $logsQuery->where('variant', $this->variant);
            $clicksQuery->where('variant', $this->variant);
        }

        $impressionsByVariant = $logsQuery
            ->select('variant', DB::raw('count(*) as imps'))
            ->groupBy('variant')
            ->pluck('imps', 'variant')
            ->map(fn (int $imps) => (int) $imps)
            ->all();

        $clicksByVariant = $clicksQuery
            ->select('variant', DB::raw('count(*) as clks'))
            ->groupBy('variant')
            ->pluck('clks', 'variant')
            ->map(fn (int $clks) => (int) $clks)
            ->all();

        $variants = Collection::make(array_keys($impressionsByVariant + $clicksByVariant))
            ->unique()
            ->sort()
            ->values();

        $this->summary = $variants
            ->map(function (string $variant) use ($impressionsByVariant, $clicksByVariant) {
                $imps = (int) ($impressionsByVariant[$variant] ?? 0);
                $clks = (int) ($clicksByVariant[$variant] ?? 0);

                return [
                    'variant' => $variant,
                    'imps' => $imps,
                    'clks' => $clks,
                    'ctr' => $imps > 0 ? round(100 * $clks / $imps, 2) : 0.0,
                ];
            })
            ->values()
            ->all();

        $this->clicksByPlacement = DB::table('rec_clicks')
            ->whereBetween('created_at', [$from, $to])
            ->select('placement', DB::raw('count(*) as clks'))
            ->groupBy('placement')
            ->orderByDesc('clks')
            ->get()
            ->map(fn (object $row) => [
                'placement' => (string) $row->placement,
                'clicks' => (int) $row->clks,
            ])
            ->all();

        $this->availablePlacements = Collection::make($this->clicksByPlacement)
            ->pluck('placement')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $totalImps = array_sum($impressionsByVariant);
        $totalViews = Schema::hasTable('device_history')
            ? (int) DB::table('device_history')
                ->whereBetween('viewed_at', [$from, $to])
                ->count()
            : 0;

        $placements = ! empty($this->availablePlacements)
            ? $this->availablePlacements
            : ['home', 'show', 'trends'];

        $funnels = Collection::make($placements)
            ->map(function (string $placement) use ($from, $to, $totalImps, $totalViews) {
                $clicks = (int) DB::table('rec_clicks')
                    ->whereBetween('created_at', [$from, $to])
                    ->where('placement', $placement)
                    ->count();

                return [
                    'label' => $placement,
                    'imps' => $totalImps,
                    'clks' => $clicks,
                    'views' => $totalViews,
                ];
            })
            ->all();

        $totalClicks = Schema::hasTable('rec_clicks')
            ? (int) DB::table('rec_clicks')->whereBetween('created_at', [$from, $to])->count()
            : 0;

        $this->funnels = array_merge($funnels, [[
            'label' => 'Итого',
            'imps' => $totalImps,
            'clks' => $totalClicks,
            'views' => $totalViews,
        ]]);
    }

    protected function tablesAvailable(): bool
    {
        return Schema::hasTable('rec_ab_logs')
            && Schema::hasTable('rec_clicks');
    }
}
