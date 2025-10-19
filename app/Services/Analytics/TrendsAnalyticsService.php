<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\Movie;
use App\Reports\TrendsReport;
use App\Support\AnalyticsCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TrendsAnalyticsService
{
    public function __construct(
        private readonly TrendsRollupService $rollup,
        private readonly AnalyticsCache $cache,
        private readonly TrendsReport $report,
    ) {}

    /**
     * @return Collection<int, object{
     *     id: int,
     *     title: string,
     *     poster_url: string|null,
     *     year: int|null,
     *     type: string,
     *     imdb_rating: float|null,
     *     imdb_votes: int|null,
     *     clicks: int|null
     * }>
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
     * @return Collection<int, array{
     *     id: int,
     *     title: string,
     *     poster_url: string|null,
     *     year: int|null,
     *     type: string,
     *     imdb_rating: float|null,
     *     imdb_votes: int|null,
     *     clicks: int|null
     * }>
     */
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

        $rows = $fallback
            ->map(static function (Movie $movie): array {
                /** @var int|null $clicks */
                $clicks = null;

                return [
                    'id' => (int) $movie->id,
                    'title' => (string) $movie->title,
                    'poster_url' => $movie->poster_url !== null ? (string) $movie->poster_url : null,
                    'year' => $movie->year !== null ? (int) $movie->year : null,
                    'type' => (string) $movie->type,
                    'imdb_rating' => $movie->imdb_rating !== null ? (float) $movie->imdb_rating : null,
                    'imdb_votes' => $movie->imdb_votes !== null ? (int) $movie->imdb_votes : null,
                    'clicks' => $clicks,
                ];
            })
            ->values()
            ->all();

        /**
         * @var Collection<int, array{
         *     id: int,
         *     title: string,
         *     poster_url: string|null,
         *     year: int|null,
         *     type: string,
         *     imdb_rating: float|null,
         *     imdb_votes: int|null,
         *     clicks: int|null
         * }> $collection
         */
        $collection = collect($rows);

        return $collection;
    }

    /**
     * @return array{
     *     items: Collection<int, object{
     *         id: int,
     *         title: string,
     *         poster_url: string|null,
     *         year: int|null,
     *         type: string,
     *         imdb_rating: float|null,
     *         imdb_votes: int|null,
     *         clicks: int|null
     *     }>,
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
     * @return Collection<int, object{
     *     id: int,
     *     title: string,
     *     poster_url: string|null,
     *     year: int|null,
     *     type: string,
     *     imdb_rating: float|null,
     *     imdb_votes: int|null,
     *     clicks: int|null
     * }>
     */
    private function buildTrendingItems(array $filters, array $period): Collection
    {
        $cached = $this->cache->rememberTrends('items', [
            'filters' => $filters,
            'period' => $period,
        ], function () use ($filters, $period): array {
            $this->rollup->ensureBackfill($period['from'], $period['to']);

            $formatItem = static function (object $item): array {
                return [
                    'id' => (int) $item->id,
                    'title' => (string) $item->title,
                    'poster_url' => $item->poster_url !== null ? (string) $item->poster_url : null,
                    'year' => $item->year !== null ? (int) $item->year : null,
                    'type' => (string) $item->type,
                    'imdb_rating' => $item->imdb_rating !== null ? (float) $item->imdb_rating : null,
                    'imdb_votes' => $item->imdb_votes !== null ? (int) $item->imdb_votes : null,
                    'clicks' => $item->clicks !== null ? (int) $item->clicks : null,
                ];
            };

            if (Schema::hasTable('rec_trending_rollups')) {
                $items = $this->report->rollupItems($period['from'], $period['to'], $filters)
                    ->map($formatItem)
                    ->values()
                    ->all();

                if ($items !== []) {
                    return $items;
                }
            }

            if (Schema::hasTable('rec_clicks')) {
                $items = $this->report->clickItems($period['from'], $period['to'], $filters)
                    ->map($formatItem)
                    ->values()
                    ->all();

                if ($items !== []) {
                    return $items;
                }
            }

            return $this->fallback(
                $filters['type'],
                $filters['genre'],
                $filters['year_from'],
                $filters['year_to'],
            )->all();
        });

        /**
         * @var list<array{
         *     id: int,
         *     title: string,
         *     poster_url: string|null,
         *     year: int|null,
         *     type: string,
         *     imdb_rating: float|null,
         *     imdb_votes: int|null,
         *     clicks: int|null
         * }> $cached
         */
        return collect($cached)
            ->map(static fn (array $row): object => (object) $row)
            ->values();
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
        $normalizedYearFrom = (int) max(0, $yearFrom);
        $normalizedYearTo = (int) max(0, $yearTo);

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

        $rangeDays = (int) max(1, $periodFrom->diffInDays($periodTo) ?: 0);
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
