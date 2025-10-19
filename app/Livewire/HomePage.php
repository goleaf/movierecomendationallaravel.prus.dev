<?php

namespace App\Livewire;

use App\Models\Movie;
use App\Services\Recommender;
use App\Support\TrendingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Рекомендации')]
class HomePage extends Component
{
    /**
     * @var Collection<int,Movie>
     */
    public Collection $recommended;

    /**
     * @var Collection<int,array{movie:Movie,clicks:int|null}>
     */
    public Collection $trending;

    public string $metaDescription = 'Подборки, тренды и рекомендации для любителей кино.';

    public function mount(Recommender $recommender, TrendingService $trendingService): void
    {
        $this->recommended = $this->loadRecommendations($recommender);
        $this->trending = $trendingService->snapshot(7, 8);
    }

    public function render(): View
    {
        return view('livewire.home-page');
    }

    protected function loadRecommendations(Recommender $recommender): Collection
    {
        $recommended = Schema::hasTable('movies')
            ? $recommender->recommendForDevice(device_id(), 12)
            : collect();

        if ($recommended->isNotEmpty() || ! Schema::hasTable('movies')) {
            return $recommended;
        }

        return Movie::query()
            ->orderByDesc('imdb_votes')
            ->orderByDesc('imdb_rating')
            ->limit(12)
            ->get();
    }
}
