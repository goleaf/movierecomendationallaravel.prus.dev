<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Movie;
use App\Services\Analytics\TrendsRollupService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;

use function image_proxy_url;

class TrendsPage extends Component
{
    #[Url]
    public int $days = 7;

    #[Url]
    public string $type = '';

    #[Url]
    public string $genre = '';

    #[Url]
    public int $yf = 0;

    #[Url]
    public int $yt = 0;

    public Collection $items;

    protected TrendsRollupService $rollup;

    public string $from;

    public string $to;

    public function boot(TrendsRollupService $rollup): void
    {
        $this->rollup = $rollup;
    }

    public function mount(): void
    {
        $this->items = collect();

        $this->sanitizeFilters();
        $this->loadItems();
    }

    public function updatedDays(): void
    {
        $sanitized = $this->sanitizeDays();
        if ($sanitized !== $this->days) {
            $this->days = $sanitized;

            return;
        }

        $this->loadItems();
    }

    public function updatedType(): void
    {
        $sanitized = $this->sanitizeType();
        if ($sanitized !== $this->type) {
            $this->type = $sanitized;

            return;
        }

        $this->loadItems();
    }

    public function updatedGenre(): void
    {
        $sanitized = $this->sanitizeGenre();
        if ($sanitized !== $this->genre) {
            $this->genre = $sanitized;

            return;
        }

        $this->loadItems();
    }

    public function updatedYf(): void
    {
        $sanitized = $this->sanitizeYear($this->yf);
        if ($sanitized !== $this->yf) {
            $this->yf = $sanitized;

            return;
        }

        $this->loadItems();
    }

    public function updatedYt(): void
    {
        $sanitized = $this->sanitizeYear($this->yt);
        if ($sanitized !== $this->yt) {
            $this->yt = $sanitized;

            return;
        }

        $this->loadItems();
    }

    protected function sanitizeFilters(): void
    {
        $this->days = $this->sanitizeDays();
        $this->type = $this->sanitizeType();
        $this->genre = $this->sanitizeGenre();
        $this->yf = $this->sanitizeYear($this->yf);
        $this->yt = $this->sanitizeYear($this->yt);
    }

    protected function sanitizeDays(): int
    {
        return max(1, min(30, $this->days));
    }

    protected function sanitizeType(): string
    {
        $type = trim($this->type);
        $allowed = ['', 'movie', 'series', 'animation'];

        return in_array($type, $allowed, true) ? $type : '';
    }

    protected function sanitizeGenre(): string
    {
        $genre = trim($this->genre);

        return mb_substr($genre, 0, 50);
    }

    protected function sanitizeYear(int $year): int
    {
        if ($year < 0) {
            return 0;
        }

        return min(2100, $year);
    }

    protected function loadItems(): void
    {
        $fromDate = now()->copy()->subDays($this->days)->startOfDay()->toImmutable();
        $toDate = now()->endOfDay()->toImmutable();

        $this->from = $fromDate->toDateString();
        $this->to = $toDate->toDateString();

        $items = collect();

        $this->rollup->ensureBackfill($fromDate, $toDate);

        if (Schema::hasTable('rec_trending_rollups')) {
            $query = DB::table('rec_trending_rollups')
                ->join('movies', 'movies.id', '=', 'rec_trending_rollups.movie_id')
                ->selectRaw('movies.id, movies.title, movies.poster_url, movies.year, movies.type, movies.imdb_rating, movies.imdb_votes, sum(rec_trending_rollups.clicks) as clicks')
                ->whereBetween('rec_trending_rollups.captured_on', [$fromDate->toDateString(), $toDate->toDateString()])
                ->groupBy('movies.id', 'movies.title', 'movies.poster_url', 'movies.year', 'movies.type', 'movies.imdb_rating', 'movies.imdb_votes')
                ->orderByDesc('clicks');

            if ($this->type !== '') {
                $query->where('movies.type', $this->type);
            }

            if ($this->genre !== '') {
                $query->whereJsonContains('movies.genres', $this->genre);
            }

            if ($this->yf > 0) {
                $query->where('movies.year', '>=', $this->yf);
            }

            if ($this->yt > 0) {
                $query->where('movies.year', '<=', $this->yt);
            }

            $items = $query->limit(40)->get()->map(static function ($item) {
                return [
                    'id' => (int) $item->id,
                    'title' => $item->title,
                    'poster_url' => image_proxy_url($item->poster_url),
                    'year' => $item->year,
                    'type' => $item->type,
                    'imdb_rating' => $item->imdb_rating,
                    'imdb_votes' => $item->imdb_votes,
                    'clicks' => (int) $item->clicks,
                ];
            });
        }

        if ($items->isEmpty() && Schema::hasTable('rec_clicks')) {
            $query = DB::table('rec_clicks')
                ->join('movies', 'movies.id', '=', 'rec_clicks.movie_id')
                ->selectRaw('movies.id, movies.title, movies.poster_url, movies.year, movies.type, movies.imdb_rating, movies.imdb_votes, count(*) as clicks')
                ->whereBetween('rec_clicks.created_at', [$fromDate->toDateTimeString(), $toDate->toDateTimeString()])
                ->groupBy('movies.id', 'movies.title', 'movies.poster_url', 'movies.year', 'movies.type', 'movies.imdb_rating', 'movies.imdb_votes')
                ->orderByDesc('clicks');

            if ($this->type !== '') {
                $query->where('movies.type', $this->type);
            }

            if ($this->genre !== '') {
                $query->whereJsonContains('movies.genres', $this->genre);
            }

            if ($this->yf > 0) {
                $query->where('movies.year', '>=', $this->yf);
            }

            if ($this->yt > 0) {
                $query->where('movies.year', '<=', $this->yt);
            }

            $items = $query->limit(40)->get()->map(static function ($item) {
                return [
                    'id' => (int) $item->id,
                    'title' => $item->title,
                    'poster_url' => image_proxy_url($item->poster_url),
                    'year' => $item->year,
                    'type' => $item->type,
                    'imdb_rating' => $item->imdb_rating,
                    'imdb_votes' => $item->imdb_votes,
                    'clicks' => (int) $item->clicks,
                ];
            });
        }

        if ($items->isEmpty()) {
            $fallback = Movie::query()
                ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
                ->when($this->genre !== '', fn ($query) => $query->whereJsonContains('genres', $this->genre))
                ->when($this->yf > 0, fn ($query) => $query->where('year', '>=', $this->yf))
                ->when($this->yt > 0, fn ($query) => $query->where('year', '<=', $this->yt))
                ->orderByDesc('imdb_votes')
                ->orderByDesc('imdb_rating')
                ->limit(40)
                ->get();

            $items = $fallback->map(static function (Movie $movie) {
                return [
                    'id' => $movie->id,
                    'title' => $movie->title,
                    'poster_url' => $movie->poster_url,
                    'year' => $movie->year,
                    'type' => $movie->type,
                    'imdb_rating' => $movie->imdb_rating,
                    'imdb_votes' => $movie->imdb_votes,
                    'clicks' => null,
                ];
            });
        }

        $this->items = $items->values();
    }

    public function render(): View
    {
        return view('livewire.trends-page')->layout('layouts.app', [
            'title' => 'Тренды рекомендаций',
            'metaDescription' => 'Статистика переходов по рекомендациям MovieRec',
        ]);
    }
}
