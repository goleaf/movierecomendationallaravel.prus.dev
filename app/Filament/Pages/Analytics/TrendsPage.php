<?php

namespace App\Filament\Pages\Analytics;

use App\Support\TrendingService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class TrendsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-line-square';

    protected static string $view = 'filament.analytics.trends';

    protected static ?string $navigationLabel = 'Trends';

    protected static ?string $navigationGroup = 'Analytics';

    #[Url(as: 'days')]
    public int $days = 7;

    /**
     * @var Collection<int,array{id:int,title:string,poster_url:?string,year:?int,type:?string,imdb_rating:?float,imdb_votes:?int,clicks:?int}>
     */
    public Collection $items;

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(TrendingService $service): void
    {
        $this->items = collect();
        $this->loadData($service);
    }

    public function updatedDays(): void
    {
        $this->days = max(1, min(30, (int) $this->days));
        $this->loadData(app(TrendingService::class));
    }

    protected function loadData(TrendingService $service): void
    {
        [$this->fromDate, $this->toDate] = $service->rangeDates($this->days);

        $this->items = $service->filtered($this->days, '', '', null, null, 24);
    }
}
