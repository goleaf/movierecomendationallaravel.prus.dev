<?php

namespace App\Livewire;

use App\Support\TrendingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Тренды рекомендаций')]
class TrendsPage extends Component
{
    #[Url(as: 'days')]
    public int $days = 7;

    #[Url(as: 'type')]
    public string $type = '';

    #[Url(as: 'genre')]
    public string $genre = '';

    #[Url(as: 'yf')]
    public ?int $yf = null;

    #[Url(as: 'yt')]
    public ?int $yt = null;

    /**
     * @var Collection<int,array{id:int,title:string,poster_url:?string,year:?int,type:?string,imdb_rating:?float,imdb_votes:?int,clicks:?int}>
     */
    public Collection $items;

    public string $fromDate = '';

    public string $toDate = '';

    public string $metaDescription = 'Следите за трендами рекомендаций и динамикой кликов.';

    public array $availableTypes = [
        '' => 'Тип контента',
        'movie' => 'Фильмы',
        'series' => 'Сериалы',
        'animation' => 'Мультфильмы',
    ];

    public function mount(TrendingService $service): void
    {
        $this->normalize();
        $this->loadItems($service);
    }

    public function applyFilters(): void
    {
        $this->normalize();
        $this->loadItems(app(TrendingService::class));
    }

    public function render(): View
    {
        return view('livewire.trends-page');
    }

    protected function normalize(): void
    {
        $this->days = max(1, min(30, (int) $this->days));
        $this->type = trim($this->type);
        $this->genre = trim($this->genre);

        $this->yf = $this->yf !== null ? max(0, (int) $this->yf) : null;
        $this->yt = $this->yt !== null ? max(0, (int) $this->yt) : null;
    }

    protected function loadItems(TrendingService $service): void
    {
        [$this->fromDate, $this->toDate] = $service->rangeDates($this->days);

        $this->items = $service->filtered(
            $this->days,
            $this->type,
            $this->genre,
            $this->yf,
            $this->yt,
        );
    }
}
