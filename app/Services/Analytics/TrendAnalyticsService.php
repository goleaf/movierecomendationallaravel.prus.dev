<?php

namespace App\Services\Analytics;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrendAnalyticsService
{
    /**
     * @return array{
     *     period: array{from: string, to: string, days: int},
     *     filters: array{type: string, genre: string, year_from: int, year_to: int},
     *     items: Collection<int, object>
     * }
     */
    public function getTrends(int $days, string $type, string $genre, int $yearFrom, int $yearTo): array
    {
        $days = max(1, min(30, $days));
        $from = CarbonImmutable::now()->subDays($days)->startOfDay();
        $to = CarbonImmutable::now()->endOfDay();

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

            if ($yearFrom > 0) {
                $query->where('movies.year', '>=', $yearFrom);
            }

            if ($yearTo > 0) {
                $query->where('movies.year', '<=', $yearTo);
            }

            $items = $query->limit(40)->get();
        }

        if ($items->isEmpty()) {
            $fallback = Movie::query()
                ->when($type !== '', static fn ($q) => $q->where('type', $type))
                ->when($genre !== '', static fn ($q) => $q->whereJsonContains('genres', $genre))
                ->when($yearFrom > 0, static fn ($q) => $q->where('year', '>=', $yearFrom))
                ->when($yearTo > 0, static fn ($q) => $q->where('year', '<=', $yearTo))
                ->orderByDesc('imdb_votes')
                ->orderByDesc('imdb_rating')
                ->limit(40)
                ->get();

            $items = $fallback->map(static fn (Movie $movie) => (object) [
                'id' => $movie->id,
                'title' => $movie->title,
                'poster_url' => $movie->poster_url,
                'year' => $movie->year,
                'type' => $movie->type,
                'imdb_rating' => $movie->imdb_rating,
                'imdb_votes' => $movie->imdb_votes,
                'clicks' => null,
            ]);
        }

        return [
            'period' => [
                'from' => Str::substr($from->toDateString(), 0, 10),
                'to' => Str::substr($to->toDateString(), 0, 10),
                'days' => $days,
            ],
            'filters' => [
                'type' => $type,
                'genre' => $genre,
                'year_from' => $yearFrom,
                'year_to' => $yearTo,
            ],
            'items' => $items,
        ];
    }
}
