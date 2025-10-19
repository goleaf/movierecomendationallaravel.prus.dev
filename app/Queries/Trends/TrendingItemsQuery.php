<?php

declare(strict_types=1);

namespace App\Queries\Trends;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TrendingItemsQuery
{
    /**
     * @param  array{type: string, genre: string, year_from: int, year_to: int}  $filters
     */
    public function rollups(CarbonImmutable $from, CarbonImmutable $to, array $filters): Builder
    {
        $range = [
            $from->toDateString(),
            $to->toDateString(),
        ];

        $query = DB::table('rec_trending_rollups as rollups')
            ->join('movies', 'movies.id', '=', 'rollups.movie_id')
            ->selectRaw('movies.id, movies.title, movies.poster_url, movies.year, movies.type, movies.imdb_rating, movies.imdb_votes, sum(rollups.clicks) as clicks')
            ->whereBetween('rollups.captured_on', $range)
            ->groupBy('movies.id', 'movies.title', 'movies.poster_url', 'movies.year', 'movies.type', 'movies.imdb_rating', 'movies.imdb_votes')
            ->orderByDesc('clicks')
            ->limit(40);

        return $this->applyFilters($query, $filters);
    }

    /**
     * @param  array{type: string, genre: string, year_from: int, year_to: int}  $filters
     */
    public function clicks(CarbonImmutable $from, CarbonImmutable $to, array $filters): Builder
    {
        $range = [
            $from->startOfDay()->format('Y-m-d H:i:s'),
            $to->endOfDay()->format('Y-m-d H:i:s'),
        ];

        $query = DB::table('rec_clicks as clicks')
            ->join('movies', 'movies.id', '=', 'clicks.movie_id')
            ->selectRaw('movies.id, movies.title, movies.poster_url, movies.year, movies.type, movies.imdb_rating, movies.imdb_votes, count(*) as clicks')
            ->whereBetween('clicks.created_at', $range)
            ->groupBy('movies.id', 'movies.title', 'movies.poster_url', 'movies.year', 'movies.type', 'movies.imdb_rating', 'movies.imdb_votes')
            ->orderByDesc('clicks')
            ->limit(40);

        return $this->applyFilters($query, $filters);
    }

    /**
     * @param  array{type: string, genre: string, year_from: int, year_to: int}  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['type'] !== '', static fn (Builder $builder) => $builder->where('movies.type', $filters['type']))
            ->when($filters['genre'] !== '', static fn (Builder $builder) => $builder->whereJsonContains('movies.genres', $filters['genre']))
            ->when($filters['year_from'] > 0, static fn (Builder $builder) => $builder->where('movies.year', '>=', $filters['year_from']))
            ->when($filters['year_to'] > 0, static fn (Builder $builder) => $builder->where('movies.year', '<=', $filters['year_to']));
    }
}
