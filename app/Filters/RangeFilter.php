<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;

/**
 * Defines the strategy used when applying range conditions to a query builder.
 */
enum RangeFilterMethod: string
{
    /**
     * Use {@see EloquentBuilder::whereBetween()} when the caller provides literal bounds for a column.
     */
    case Between = 'whereBetween';

    /**
     * Use {@see EloquentBuilder::whereValueBetween()} when a literal value must fall inside a stored column range.
     */
    case ValueBetween = 'whereValueBetween';

    /**
     * Use {@see EloquentBuilder::whereBetweenColumns()} when a column must sit between two other columns.
     */
    case BetweenColumns = 'whereBetweenColumns';
}

final class RangeFilter
{
    private function __construct(private EloquentBuilder|BaseQueryBuilder $builder) {}

    public static function for(EloquentBuilder|BaseQueryBuilder $builder): self
    {
        return new self($builder);
    }

    /**
     * Apply a {@see RangeFilterMethod::Between} constraint for literal bounds.
     */
    public function forColumn(string $column, mixed $from, mixed $to): EloquentBuilder|BaseQueryBuilder
    {
        if ($from !== null && $to !== null) {
            $this->builder->whereBetween($column, [$from, $to]);

            return $this->builder;
        }

        if ($from !== null) {
            $this->builder->where($column, '>=', $from);
        }

        if ($to !== null) {
            $this->builder->where($column, '<=', $to);
        }

        return $this->builder;
    }

    /**
     * Apply a {@see RangeFilterMethod::ValueBetween} constraint for literal values against stored columns.
     */
    public function forValue(mixed $value, ?string $lowerColumn, ?string $upperColumn): EloquentBuilder|BaseQueryBuilder
    {
        if ($lowerColumn === null && $upperColumn === null) {
            return $this->builder;
        }

        if ($lowerColumn !== null && $upperColumn !== null) {
            $this->builder->whereValueBetween($value, [$lowerColumn, $upperColumn]);

            return $this->builder;
        }

        if ($lowerColumn !== null) {
            $this->builder->where($lowerColumn, '<=', $value);
        }

        if ($upperColumn !== null) {
            $this->builder->where($upperColumn, '>=', $value);
        }

        return $this->builder;
    }

    /**
     * Apply a {@see RangeFilterMethod::BetweenColumns} constraint for column to column comparisons.
     */
    public function forColumns(string $column, ?string $lowerColumn, ?string $upperColumn): EloquentBuilder|BaseQueryBuilder
    {
        if ($lowerColumn === null && $upperColumn === null) {
            return $this->builder;
        }

        if ($lowerColumn !== null && $upperColumn !== null) {
            $this->builder->whereBetweenColumns($column, [$lowerColumn, $upperColumn]);

            return $this->builder;
        }

        if ($lowerColumn !== null) {
            $this->builder->whereColumn($column, '>=', $lowerColumn);
        }

        if ($upperColumn !== null) {
            $this->builder->whereColumn($column, '<=', $upperColumn);
        }

        return $this->builder;
    }
}
