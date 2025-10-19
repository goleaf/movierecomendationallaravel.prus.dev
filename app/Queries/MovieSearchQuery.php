<?php

declare(strict_types=1);

namespace App\Queries;

use App\Filters\RangeFilter;
use App\Models\Movie;
use App\Search\QueryBuilderExtensions;
use App\Support\MovieSearchFilters;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\SQLiteConnection;

final class MovieSearchQuery
{
    private Builder $builder;

    /**
     * @var array<int, string>
     */
    private array $searchableColumns = [
        'title',
        'plot',
        'translations->title->en',
        'translations->title->ru',
    ];

    public function __construct(?Builder $builder = null)
    {
        QueryBuilderExtensions::register();

        $this->builder = $builder ?? Movie::query();
    }

    public static function forFilters(MovieSearchFilters $filters, ?Builder $builder = null): Builder
    {
        return (new self($builder))->apply($filters);
    }

    public function apply(MovieSearchFilters $filters): Builder
    {
        $this->applySearch($filters->query);
        $this->applyType($filters->type);
        $this->applyGenre($filters->genre);
        $this->applyYearBounds($filters->yearFrom, $filters->yearTo);
        $this->applyRuntime($filters->runtime);
        $this->applyRating($filters->rating);

        return $this->builder;
    }

    public function builder(): Builder
    {
        return $this->builder;
    }

    private function applySearch(string $term): void
    {
        $tokens = $this->tokenize($term);

        if ($tokens === []) {
            return;
        }

        $columns = $this->searchableColumns;

        $this->builder->whereAll(array_map(
            function (string $token) use ($columns): callable {
                $pattern = $this->buildLikePattern($token);

                return function (Builder|BaseQueryBuilder $query) use ($columns, $pattern): void {
                    $query->whereAny(array_map(
                        function (string $column) use ($pattern): callable {
                            return function (Builder|BaseQueryBuilder $columnQuery) use ($column, $pattern): void {
                                $this->applyLike($columnQuery, $column, $pattern);
                            };
                        },
                        $columns
                    ));
                };
            },
            $tokens
        ));
    }

    private function applyType(?string $type): void
    {
        if ($type !== null) {
            $this->builder->where('type', $type);
        }
    }

    private function applyGenre(?string $genre): void
    {
        if ($genre !== null) {
            $this->builder->whereJsonContains('genres', $genre);
        }
    }

    private function applyYearBounds(?int $from, ?int $to): void
    {
        RangeFilter::for($this->builder)->forColumn('year', $from, $to);
    }

    /**
     * @param  array{min:int|null,max:int|null}|null  $range
     */
    private function applyRuntime(?array $range): void
    {
        if ($range === null) {
            return;
        }

        RangeFilter::for($this->builder)->forColumn('runtime_min', $range['min'] ?? null, $range['max'] ?? null);
    }

    /**
     * @param  array{min:float|null,max:float|null}|null  $range
     */
    private function applyRating(?array $range): void
    {
        if ($range === null) {
            return;
        }

        RangeFilter::for($this->builder)->forColumn('imdb_rating', $range['min'] ?? null, $range['max'] ?? null);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $value) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    private function buildLikePattern(string $value): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return '%'.$escaped.'%';
    }

    private function applyLike(Builder|BaseQueryBuilder $builder, string $column, string $pattern): void
    {
        $query = $builder instanceof Builder ? $builder->getQuery() : $builder;
        $connection = $query->getConnection();

        if ($this->requiresLowercaseFallback($connection)) {
            $wrapped = $query->getGrammar()->wrap($column);

            $builder->whereRaw('lower('.$wrapped.') like ?', [mb_strtolower($pattern, 'UTF-8')]);

            return;
        }

        $builder->whereLike($column, $pattern);
    }

    private function requiresLowercaseFallback(ConnectionInterface $connection): bool
    {
        return $connection instanceof SQLiteConnection;
    }
}
