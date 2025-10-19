<?php

namespace App\Support;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrendingService
{
    /**
     * @return Collection<int, array{movie: Movie, clicks: int|null}>
     */
    public function snapshot(int $days, int $limit = 8): Collection
    {
        if (! Schema::hasTable('movies')) {
            return collect();
        }

        [$from, $to] = $this->timeframe($days);

        if (! Schema::hasTable('rec_clicks')) {
            return $this->fallbackSnapshot($limit);
        }

        /** @var Collection<int, int> $top */
        $top = DB::table('rec_clicks')
            ->selectRaw('movie_id, count(*) as clicks')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('movie_id')
            ->orderByDesc('clicks')
            ->limit($limit)
            ->pluck('clicks', 'movie_id');

        if ($top->isEmpty()) {
            return $this->fallbackSnapshot($limit);
        }

        /** @var Collection<int, Movie> $movies */
        $movies = Movie::query()
            ->whereIn('id', $top->keys()->all())
            ->get()
            ->keyBy('id');

        $entries = $top->map(function (int $clicks, int $movieId) use ($movies): ?array {
            $movie = $movies->get($movieId);

            if ($movie === null) {
                return null;
            }

            return [
                'movie' => $movie,
                'clicks' => $clicks,
            ];
        })->filter();

        /** @var Collection<int, array{movie: Movie, clicks: int}> $entries */
        $entries = $entries;

        return $entries->values();
    }

    /**
     * @return Collection<int, array{movie: Movie, clicks: int|null}>
     */
    protected function fallbackSnapshot(int $limit): Collection
    {
        if (! Schema::hasTable('movies')) {
            return collect();
        }

        /** @var Collection<int, Movie> $movies */
        $movies = Movie::query()
            ->orderByDesc('imdb_votes')
            ->orderByDesc('imdb_rating')
            ->limit($limit)
            ->get();

        return $movies
            ->map(static fn (Movie $movie): array => [
                'movie' => $movie,
                'clicks' => null,
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     title: string,
     *     poster_url: string|null,
     *     year: int|null,
     *     type: string|null,
     *     imdb_rating: float|null,
     *     imdb_votes: int|null,
     *     clicks: int|null,
     * }>
     */
    public function filtered(
        int $days,
        string $type = '',
        string $genre = '',
        ?int $yearFrom = null,
        ?int $yearTo = null,
        int $limit = 40
    ): Collection {
        if (! Schema::hasTable('movies')) {
            return collect();
        }

        [$from, $to] = $this->timeframe($days);

        /** @var Collection<int, \stdClass> $items */
        $items = collect();

        if (Schema::hasTable('rec_clicks')) {
            $query = DB::table('rec_clicks')
                ->join('movies', 'movies.id', '=', 'rec_clicks.movie_id')
                ->selectRaw('movies.id, movies.title, movies.poster_url, movies.year, movies.type, movies.imdb_rating, movies.imdb_votes, count(*) as clicks')
                ->whereBetween('rec_clicks.created_at', [$from, $to])
                ->groupBy('movies.id', 'movies.title', 'movies.poster_url', 'movies.year', 'movies.type', 'movies.imdb_rating', 'movies.imdb_votes')
                ->orderByDesc('clicks');

            if ($type !== '') {
                $query->where('movies.type', $type);
            }

            if ($genre !== '') {
                $query->whereJsonContains('movies.genres', $genre);
            }

            if ($yearFrom !== null) {
                $query->where('movies.year', '>=', $yearFrom);
            }

            if ($yearTo !== null) {
                $query->where('movies.year', '<=', $yearTo);
            }

            /** @var Collection<int, \stdClass> $items */
            $items = $query->limit($limit)->get();
        }

        if ($items->isNotEmpty()) {
            return $items->map(static function (\stdClass $item): array {
                return [
                    'id' => (int) $item->id,
                    'title' => (string) $item->title,
                    'poster_url' => $item->poster_url !== null ? (string) $item->poster_url : null,
                    'year' => $item->year !== null ? (int) $item->year : null,
                    'type' => $item->type !== null ? (string) $item->type : null,
                    'imdb_rating' => $item->imdb_rating !== null ? (float) $item->imdb_rating : null,
                    'imdb_votes' => $item->imdb_votes !== null ? (int) $item->imdb_votes : null,
                    'clicks' => $item->clicks !== null ? (int) $item->clicks : null,
                ];
            });
        }

        /** @var Collection<int, Movie> $fallback */
        $fallback = Movie::query()
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($genre !== '', fn ($query) => $query->whereJsonContains('genres', $genre))
            ->when($yearFrom !== null, fn ($query) => $query->where('year', '>=', $yearFrom))
            ->when($yearTo !== null, fn ($query) => $query->where('year', '<=', $yearTo))
            ->orderByDesc('imdb_votes')
            ->orderByDesc('imdb_rating')
            ->limit($limit)
            ->get();

        return $fallback
            ->map(static fn (Movie $movie): array => [
                'id' => $movie->id,
                'title' => $movie->title,
                'poster_url' => $movie->poster_url,
                'year' => $movie->year,
                'type' => $movie->type,
                'imdb_rating' => $movie->imdb_rating,
                'imdb_votes' => $movie->imdb_votes,
                'clicks' => null,
            ])
            ->values();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function rangeDates(int $days): array
    {
        [$from, $to] = $this->timeframe($days);

        return [
            substr($from, 0, 10),
            substr($to, 0, 10),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function timeframe(int $days): array
    {
        $clamped = max(1, min(30, $days));

        $from = CarbonImmutable::now()->subDays($clamped)->startOfDay()->format('Y-m-d H:i:s');
        $to = CarbonImmutable::now()->endOfDay()->format('Y-m-d H:i:s');

        return [$from, $to];
    }
}
