<?php

namespace App\Filament\Pages\Analytics;

use App\Services\Analytics\TrendsAnalyticsService;
use Filament\Pages\Page;

class TrendsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-line-square';
    protected static string $view = 'filament.analytics.trends';
    protected static ?string $navigationLabel = 'Trends';
    protected static ?string $navigationGroup = 'Analytics';
    protected static ?string $slug = 'trends';

    public int $days = 7;
    public string $type = '';
    public string $genre = '';
    public ?int $yearFrom = null;
    public ?int $yearTo = null;
    public bool $showAdvancedFilters = false;

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public array $dayOptions = [3, 7, 14, 30];

    /** @var array<string, string> */
    public array $typeOptions = [];

    public string $fromDate;
    public string $toDate;

    public function mount(): void
    {
        $this->typeOptions = [
            '' => __('admin.trends.type_placeholder'),
            'movie' => __('admin.trends.types.movie'),
            'series' => __('admin.trends.types.series'),
            'animation' => __('admin.trends.types.animation'),
        ];

        $this->refreshData();
    }

    public function refreshData(): void
    {
        $this->days = max(1, min(30, (int) $this->days));

        $items = app(TrendsAnalyticsService::class)->trending(
            $this->days,
            $this->type,
            $this->genre,
            (int) ($this->yearFrom ?? 0),
            (int) ($this->yearTo ?? 0),
        );

        $this->items = $items->map(fn ($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'poster_url' => $item->poster_url,
            'year' => $item->year,
            'type' => $item->type,
            'imdb_rating' => $item->imdb_rating,
            'imdb_votes' => $item->imdb_votes,
            'clicks' => $item->clicks,
        ])->all();

        $this->fromDate = now()->subDays($this->days)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function updated($property): void
    {
        if (in_array($property, ['days', 'type', 'genre', 'yearFrom', 'yearTo'], true)) {
            if (! $this->showAdvancedFilters && in_array($property, ['genre', 'yearFrom', 'yearTo'], true)) {
                return;
            }

            $this->refreshData();
        }
    }
}
