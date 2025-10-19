<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Services\Analytics\TrendsAnalyticsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

use function image_proxy_url;

class TrendsBrowser extends Component
{
    public bool $showAdvanced = false;

    public array $filters = [];

    /** @var array{from: string, to: string, days: int} */
    public array $period = ['from' => '', 'to' => '', 'days' => 7];

    /** @var array<int, array{id: int, title: string, poster_url: ?string, year: ?int, type: ?string, imdb_rating: ?float, imdb_votes: ?int, clicks: ?int}> */
    public array $items = [];

    protected TrendsAnalyticsService $service;

    public function boot(TrendsAnalyticsService $service): void
    {
        $this->service = $service;
    }

    public function mount(bool $showAdvanced = false): void
    {
        $this->showAdvanced = $showAdvanced;

        $this->filters = [
            'days' => 7,
            'type' => '',
            'genre' => '',
            'year_from' => '',
            'year_to' => '',
        ];

        $this->refreshTrends();
    }

    public function apply(): void
    {
        $this->validate();
        $this->refreshTrends();
    }

    protected function rules(): array
    {
        return [
            'filters.days' => ['required', 'integer', 'min:1', 'max:30'],
            'filters.type' => ['nullable', 'string'],
            'filters.genre' => ['nullable', 'string'],
            'filters.year_from' => ['nullable', 'integer', 'min:0'],
            'filters.year_to' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function refreshTrends(): void
    {
        $result = $this->service->getTrendsData(
            (int) $this->filters['days'],
            (string) ($this->filters['type'] ?? ''),
            (string) ($this->filters['genre'] ?? ''),
            (int) ($this->filters['year_from'] ?: 0),
            (int) ($this->filters['year_to'] ?: 0),
        );

        $this->period = $result['period'];
        $this->filters['days'] = $this->period['days'];
        $this->filters['type'] = $result['filters']['type'];
        $this->filters['genre'] = $result['filters']['genre'];
        $this->filters['year_from'] = $result['filters']['year_from'] ?: '';
        $this->filters['year_to'] = $result['filters']['year_to'] ?: '';

        $this->items = $result['items']->map(static function ($item): array {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'poster_url' => image_proxy_url($item->poster_url),
                'year' => $item->year,
                'type' => $item->type,
                'imdb_rating' => $item->imdb_rating,
                'imdb_votes' => $item->imdb_votes,
                'clicks' => $item->clicks,
            ];
        })->all();
    }

    public function render(): View
    {
        return view('livewire.analytics.trends-browser');
    }
}
