<?php

namespace App\Livewire;

use App\Models\Movie;
use App\Services\Recommender;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;

class HomePage extends Component
{
    public Collection $recommended;

    public Collection $trending;

    public function mount(Recommender $recommender): void
    {
        $hasMoviesTable = Schema::hasTable('movies');

        if ($hasMoviesTable) {
            $deviceId = $this->resolveDeviceId();

            $recommendation = $recommender->recommendForDevice($deviceId, 12);
            $this->recommended = $recommendation->movies();

            if ($this->recommended->isEmpty()) {
                $this->recommended = Movie::query()
                    ->orderByDesc('imdb_votes')
                    ->orderByDesc('imdb_rating')
                    ->limit(12)
                    ->get();
            }
        } else {
            $this->recommended = collect();
        }

        $this->trending = $this->fetchTrendingSnapshot();

        if ($this->trending->isEmpty()) {
            $this->trending = $hasMoviesTable
                ? Movie::query()
                    ->orderByDesc('imdb_votes')
                    ->orderByDesc('imdb_rating')
                    ->limit(8)
                    ->get()
                    ->map(static fn (Movie $movie) => [
                        'movie' => $movie,
                        'clicks' => null,
                    ])
                : collect();
        }
    }

    protected function resolveDeviceId(): string
    {
        $key = 'did';
        $id = (string) request()->cookie($key, '');

        if ($id === '') {
            $id = 'd_'.Str::uuid()->toString();
            Cookie::queue(Cookie::make($key, $id, 60 * 24 * 365 * 5));
        }

        return $id;
    }

    /**
     * @return Collection<int, array{movie: Movie, clicks: int|null}>
     */
    protected function fetchTrendingSnapshot(): Collection
    {
        if (! Schema::hasTable('rec_clicks') || ! Schema::hasTable('movies')) {
            return collect();
        }

        $from = now()->copy()->subDays(7)->startOfDay();
        $to = now()->endOfDay();

        $top = DB::table('rec_clicks')
            ->selectRaw('movie_id, count(*) as clicks')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->groupBy('movie_id')
            ->orderByDesc('clicks')
            ->limit(8)
            ->pluck('clicks', 'movie_id');

        if ($top->isEmpty()) {
            return collect();
        }

        $movies = Movie::query()
            ->whereIn('id', $top->keys()->all())
            ->get()
            ->keyBy('id');

        return $top
            ->map(static function (int $clicks, int $movieId) use ($movies) {
                $movie = $movies->get($movieId);

                if ($movie === null) {
                    return null;
                }

                return [
                    'movie' => $movie,
                    'clicks' => $clicks,
                ];
            })
            ->filter()
            ->values();
    }

    public function render(): View
    {
        return view('livewire.home-page')->layout('layouts.app', [
            'title' => 'Рекомендации',
            'metaDescription' => 'Подборки, тренды и рекомендации',
        ]);
    }
}
