<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class MovieIndexFilters
{
    public const SORT_POPULAR = 'popular';

    public const SORT_RATING = 'rating';

    public const SORT_NEWEST = 'newest';

    public const ALLOWED_SORTS = [
        self::SORT_POPULAR,
        self::SORT_RATING,
        self::SORT_NEWEST,
    ];

    public const DEFAULT_SORT = self::SORT_POPULAR;

    private const MIN_YEAR = 1870;

    private const MAX_YEAR = 2100;

    public function __construct(
        public readonly ?string $query,
        /**
         * @var array<int, string>
         */
        public readonly array $genres,
        public readonly ?int $yearFrom,
        public readonly ?int $yearTo,
        public readonly string $sort,
        public readonly int $page,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $query = self::normalizeQuery($input['q'] ?? null);
        $genres = self::normalizeGenres($input['genres'] ?? []);
        $yearFrom = self::normalizeYear($input['year_from'] ?? null);
        $yearTo = self::normalizeYear($input['year_to'] ?? null);
        $sort = self::normalizeSort($input['sort'] ?? null);
        $page = self::normalizePage($input['page'] ?? null);

        if ($yearFrom !== null && $yearTo !== null && $yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        return new self($query, $genres, $yearFrom, $yearTo, $sort, $page);
    }

    public function apply(Builder $builder): Builder
    {
        if ($this->query !== null) {
            $search = $this->query;

            $builder->where(static function (Builder $where) use ($search): void {
                $where->where('title', 'like', "%{$search}%")
                    ->orWhere('imdb_tt', $search);
            });
        }

        foreach ($this->genresAsLabels() as $genre) {
            $builder->whereJsonContains('genres', $genre);
        }

        if ($this->yearFrom !== null) {
            $builder->where('year', '>=', $this->yearFrom);
        }

        if ($this->yearTo !== null) {
            $builder->where('year', '<=', $this->yearTo);
        }

        return $this->applySort($builder);
    }

    /**
     * @return array<int, string>
     */
    public function genresAsLabels(): array
    {
        return array_map(static fn (string $slug): string => Str::of($slug)->replace('-', ' ')->value(), $this->genres);
    }

    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'genres' => $this->genres,
            'year_from' => $this->yearFrom,
            'year_to' => $this->yearTo,
            'sort' => $this->sort,
            'page' => $this->page,
        ];
    }

    public function toRequestPayload(): array
    {
        return [
            'q' => $this->query,
            'genres' => $this->genres,
            'year_from' => $this->yearFrom,
            'year_to' => $this->yearTo,
            'sort' => $this->sort,
            'page' => $this->page,
        ];
    }

    private static function normalizeQuery(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private static function normalizeGenres(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        return Slug::canonicalize($value);
    }

    private static function normalizeYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $year = (int) $value;

        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            return null;
        }

        return $year;
    }

    private static function normalizeSort(mixed $value): string
    {
        if (! is_string($value)) {
            return self::DEFAULT_SORT;
        }

        $sort = trim($value);

        if ($sort === '') {
            return self::DEFAULT_SORT;
        }

        return in_array($sort, self::ALLOWED_SORTS, true) ? $sort : self::DEFAULT_SORT;
    }

    private static function normalizePage(mixed $value): int
    {
        $page = (int) ($value ?? 1);

        return $page < 1 ? 1 : $page;
    }

    private function applySort(Builder $builder): Builder
    {
        return match ($this->sort) {
            self::SORT_RATING => $builder
                ->orderByDesc('imdb_rating')
                ->orderByDesc('imdb_votes'),
            self::SORT_NEWEST => $builder
                ->orderByDesc('release_date')
                ->orderByDesc('year')
                ->orderByDesc('imdb_votes'),
            default => $builder
                ->orderByDesc('imdb_votes')
                ->orderByDesc('imdb_rating'),
        };
    }
}
