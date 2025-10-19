<?php

namespace App\Services\Analytics;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrendsAnalyticsService
{
    public function trending(int $days, string $type = '', string $genre = '', int $yearFrom = 0, int $yearTo = 0): Collection
    {
        $days = max(1, min(30, $days));
        $type = trim($type);
        $genre = trim($genre);
        $yearFrom = max(0, $yearFrom);
        $yearTo = max(0, $yearTo);

        $from = CarbonImmutable::now()->subDays($days)->startOfDay();
        $to = CarbonImmutable::now()->endOfDay();

        if (Schema::hasTable('rec_clicks')) {
            $query = DB::table('rec_clicks')
                ->join('movies', 'movies.id', '=', 'rec_clicks.movie_id')
                ->selectRaw('movies.id, movies.title, movies.poster_url, movies.year, movies.type, movies.imdb_rating, movies.imdb_votes, count(*) as clicks')
                ->whereBetween('rec_clicks.created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
                ->groupBy('movies.id', 'movies.title', 'movies.poster_url', 'movies.year', 'movies.type', 'movies.imdb_rating', 'movies.imdb_votes')
                ->orderByDesc('clicks');

            if ($type !== '') {
                $query->where('movies.type', $type);
            }

            if ($genre !== '') {
                $query->whereJsonContains('movies.genres', $genre);
            }

            if ($yearFrom > 0) {
                $query->where('movies.year', '>=', $yearFrom);
            }

            if ($yearTo > 0) {
                $query->where('movies.year', '<=', $yearTo);
            }

            $items = $query->limit(40)->get();

            if ($items->isNotEmpty()) {
                return $items;
            }
        }

        return $this->fallback($type, $genre, $yearFrom, $yearTo);
    }

    private function fallback(string $type, string $genre, int $yearFrom, int $yearTo): Collection
    {
        $fallback = Movie::query()
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($genre !== '', fn ($q) => $q->whereJsonContains('genres', $genre))
            ->when($yearFrom > 0, fn ($q) => $q->where('year', '>=', $yearFrom))
            ->when($yearTo > 0, fn ($q) => $q->where('year', '<=', $yearTo))
            ->orderByDesc('imdb_votes')
            ->orderByDesc('imdb_rating')
            ->limit(40)
            ->get();

        return $fallback->map(function (Movie $movie) {
            return (object) [
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

    /**
     * @return array{
     *     items: Collection<int, object>,
     *     filters: array{days: int, type: string, genre: string, year_from: int, year_to: int},
     *     period: array{from: string, to: string, days: int}
     * }
     */
    public function getTrendsData(int $days, string $type = '', string $genre = '', int $yearFrom = 0, int $yearTo = 0): array
    {
        $normalizedDays = max(1, min(30, $days));
        $normalizedType = trim($type);
        $normalizedGenre = trim($genre);
        $normalizedYearFrom = max(0, $yearFrom);
        $normalizedYearTo = max(0, $yearTo);

        $periodFrom = CarbonImmutable::now()->subDays($normalizedDays)->startOfDay();
        $periodTo = CarbonImmutable::now()->endOfDay();

        $items = $this->trending(
            $normalizedDays,
            $normalizedType,
            $normalizedGenre,
            $normalizedYearFrom,
            $normalizedYearTo,
        );

        return [
            'items' => $items,
            'filters' => [
                'days' => $normalizedDays,
                'type' => $normalizedType,
                'genre' => $normalizedGenre,
                'year_from' => $normalizedYearFrom,
                'year_to' => $normalizedYearTo,
            ],
            'period' => [
                'from' => $periodFrom->toDateString(),
                'to' => $periodTo->toDateString(),
                'days' => $normalizedDays,
            ],
        ];
    }
}
