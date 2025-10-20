<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Movie;
use App\Search\DSL;
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
        $this->builder = DSL::for($this->builder)->search($this->searchableColumns, $term);
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
}
