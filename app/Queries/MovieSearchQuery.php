<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Movie;
use App\Search\QueryBuilderExtensions;
use App\Support\MovieSearchFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;

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
        'translations->plot->en',
        'translations->plot->ru',
        'raw->cast',
        'raw',
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

        $patternSets = array_map(fn (string $token): array => $this->buildPatternVariants($token), $tokens);

        $this->builder->whereAll(
            QueryBuilderExtensions::whereAllLikeAcrossColumns($this->searchableColumns, $patternSets)
        );
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
        $clauses = [];

        if ($from !== null) {
            $clauses[] = function (Builder|BaseQueryBuilder $query) use ($from): void {
                $query->where('year', '>=', $from);
            };
        }

        if ($to !== null) {
            $clauses[] = function (Builder|BaseQueryBuilder $query) use ($to): void {
                $query->where('year', '<=', $to);
            };
        }

        if ($clauses !== []) {
            $this->builder->whereAll($clauses);
        }
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

    /**
     * @return array<int, string>
     */
    private function buildPatternVariants(string $token): array
    {
        $variants = [$token];

        $variants[] = mb_strtolower($token, 'UTF-8');
        $variants[] = mb_convert_case(mb_strtolower($token, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $variants[] = mb_strtoupper($token, 'UTF-8');

        $patterns = array_map(fn (string $variant): string => $this->buildLikePattern($variant), $variants);

        return array_values(array_unique($patterns));
    }

}
