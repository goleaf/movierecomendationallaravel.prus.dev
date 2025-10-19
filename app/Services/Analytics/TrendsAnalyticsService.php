<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use stdClass;

class TrendsAnalyticsService
{
    /**
     * @return Collection<int, array{id: int, title: string, poster_url: string|null, year: int|null, type: string|null, imdb_rating: float|null, imdb_votes: int|null, clicks: int|null}>
     */
    public function trending(
        int $days,
        string $type = '',
        string $genre = '',
        int $yearFrom = 0,
        int $yearTo = 0,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): Collection {
        $normalized = $this->normalizeParameters($days, $type, $genre, $yearFrom, $yearTo, $from, $to);

        return $this->buildTrendingItems($normalized['filters'], $normalized['period']);
    }

    /**
     * @return Collection<int, array{id: int, title: string, poster_url: string|null, year: int|null, type: string|null, imdb_rating: float|null, imdb_votes: int|null, clicks: int|null}>
     */
    private function fallback(string $type, string $genre, int $yearFrom, int $yearTo): Collection
    {
        /** @var Collection<int, Movie> $fallback */
        $fallback = Movie::query()
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($genre !== '', fn ($q) => $q->whereJsonContains('genres', $genre))
            ->when($yearFrom > 0, fn ($q) => $q->where('year', '>=', $yearFrom))
            ->when($yearTo > 0, fn ($q) => $q->where('year', '<=', $yearTo))
            ->orderByDesc('imdb_votes')
            ->orderByDesc('imdb_rating')
            ->limit(40)
            ->get();

        return $fallback
            ->map(static function (Movie $movie): array {
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
            })
            ->values();
    }

    /**
     * @return array{
     *     items: Collection<int, array{id: int, title: string, poster_url: string|null, year: int|null, type: string|null, imdb_rating: float|null, imdb_votes: int|null, clicks: int|null}>,
     *     filters: array{days: int, type: string, genre: string, year_from: int, year_to: int},
     *     period: array{from: string, to: string, days: int}
     * }
     */
    public function getTrendsData(
        int $days,
        string $type = '',
        string $genre = '',
        int $yearFrom = 0,
        int $yearTo = 0,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): array {
        $normalized = $this->normalizeParameters($days, $type, $genre, $yearFrom, $yearTo, $from, $to);

        $items = $this->buildTrendingItems($normalized['filters'], $normalized['period']);

        return [
            'items' => $items,
            'filters' => $normalized['filters'],
            'period' => [
                'from' => $normalized['period']['from']->toDateString(),
                'to' => $normalized['period']['to']->toDateString(),
                'days' => $normalized['filters']['days'],
            ],
        ];
    }

    /**
     * @param  array{days: int, type: string, genre: string, year_from: int, year_to: int}  $filters
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $period
     * @return Collection<int, array{id: int, title: string, poster_url: string|null, year: int|null, type: string|null, imdb_rating: float|null, imdb_votes: int|null, clicks: int|null}>
     */
    private function buildTrendingItems(array $filters, array $period): Collection
    {
        if (Schema::hasTable('rec_clicks')) {
            $query = DB::table('rec_clicks')
                ->join('movies', 'movies.id', '=', 'rec_clicks.movie_id')
                ->selectRaw('movies.id, movies.title, movies.poster_url, movies.year, movies.type, movies.imdb_rating, movies.imdb_votes, count(*) as clicks')
                ->whereBetween('rec_clicks.created_at', [$period['from']->toDateTimeString(), $period['to']->toDateTimeString()])
                ->groupBy('movies.id', 'movies.title', 'movies.poster_url', 'movies.year', 'movies.type', 'movies.imdb_rating', 'movies.imdb_votes')
                ->orderByDesc('clicks');

            if ($filters['type'] !== '') {
                $query->where('movies.type', $filters['type']);
            }

            if ($filters['genre'] !== '') {
                $query->whereJsonContains('movies.genres', $filters['genre']);
            }

            if ($filters['year_from'] > 0) {
                $query->where('movies.year', '>=', $filters['year_from']);
            }

            if ($filters['year_to'] > 0) {
                $query->where('movies.year', '<=', $filters['year_to']);
            }

            $items = $query->limit(40)->get();

            if ($items->isNotEmpty()) {
                /** @var Collection<int, stdClass> $items */
                $items = $items;

                return $items
                    ->map(static function (stdClass $item): array {
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
                    })
                    ->values();
            }
        }

        return $this->fallback(
            $filters['type'],
            $filters['genre'],
            $filters['year_from'],
            $filters['year_to'],
        );
    }

    /**
     * @return array{
     *     filters: array{days: int, type: string, genre: string, year_from: int, year_to: int},
     *     period: array{from: CarbonImmutable, to: CarbonImmutable}
     * }
     */
    private function normalizeParameters(
        int $days,
        string $type,
        string $genre,
        int $yearFrom,
        int $yearTo,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): array {
        $normalizedDays = (int) max(1, min(30, $days));
        $normalizedType = trim($type);
        $normalizedGenre = trim($genre);
        $normalizedYearFrom = max(0, $yearFrom);
        $normalizedYearTo = max(0, $yearTo);

        $periodFrom = $from?->startOfDay();
        $periodTo = $to?->endOfDay();

        if ($periodFrom === null || $periodTo === null) {
            $now = CarbonImmutable::now();
            $periodFrom = $now->subDays($normalizedDays)->startOfDay();
            $periodTo = $now->endOfDay();
        }

        if ($periodFrom->greaterThan($periodTo)) {
            [$periodFrom, $periodTo] = [$periodTo, $periodFrom];
        }

        $rangeDays = (int) max(1, $periodFrom->diffInDays($periodTo));
        $normalizedDays = (int) max(1, min(30, $rangeDays));

        return [
            'filters' => [
                'days' => $normalizedDays,
                'type' => $normalizedType,
                'genre' => $normalizedGenre,
                'year_from' => $normalizedYearFrom,
                'year_to' => $normalizedYearTo,
            ],
            'period' => [
                'from' => $periodFrom,
                'to' => $periodTo,
            ],
        ];
    }
}
