<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\RecClick;
use App\Services\Recommender;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function __invoke(Recommender $recommender): View
    {
        $did = device_id();
        [
            'variant' => $recVariant,
            'recommendations' => $recommended,
        ] = $recommender->recommendForDevice($did, 12);

        if ($recommended->isEmpty()) {
            $recommended = Movie::query()
                ->orderByDesc('imdb_votes')
                ->orderByDesc('imdb_rating')
                ->limit(12)
                ->get();
        }

        $trending = $this->fetchTrendingSnapshot();
        if ($trending->isEmpty()) {
            $trending = Movie::query()
                ->orderByDesc('imdb_votes')
                ->orderByDesc('imdb_rating')
                ->limit(8)
                ->get()
                ->map(fn (Movie $movie) => [
                    'movie' => $movie,
                    'clicks' => null,
                ]);
        }

        return view('home.index', [
            'recommended' => $recommended,
            'trending' => $trending,
            'recVariant' => $recVariant,
        ]);
    }

    /**
     * @return Collection<int,array{movie:Movie,clicks:int|null}>
     */
    protected function fetchTrendingSnapshot(): Collection
    {
        if (! Schema::hasTable('rec_clicks')) {
            return collect();
        }

        $from = now()->subDays(7)->format('Y-m-d 00:00:00');
        $to = now()->format('Y-m-d 23:59:59');

        $top = RecClick::query()
            ->selectRaw('movie_id, count(*) as clicks')
            ->betweenCreatedAt($from, $to)
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
            ->map(function (int $clicks, int $movieId) use ($movies) {
                $movie = $movies->get($movieId);
                if (! $movie) {
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
}
