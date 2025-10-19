<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filters\RangeFilter;
use App\Models\Movie;
use Tests\TestCase;

class RangeFilterTest extends TestCase
{
    public function test_for_column_applies_closed_interval(): void
    {
        $builder = Movie::query();

        RangeFilter::for($builder)->forColumn('year', 1999, 2005);

        $this->assertSame('select * from "movies" where "year" between ? and ?', $builder->toSql());
        $this->assertSame([1999, 2005], $builder->getBindings());
    }

    public function test_for_column_handles_open_lower_bound(): void
    {
        $builder = Movie::query();

        RangeFilter::for($builder)->forColumn('year', 1984, null);

        $this->assertSame('select * from "movies" where "year" >= ?', $builder->toSql());
        $this->assertSame([1984], $builder->getBindings());
    }

    public function test_for_column_handles_open_upper_bound(): void
    {
        $builder = Movie::query();

        RangeFilter::for($builder)->forColumn('year', null, 2020);

        $this->assertSame('select * from "movies" where "year" <= ?', $builder->toSql());
        $this->assertSame([2020], $builder->getBindings());
    }

    public function test_for_column_skips_when_bounds_absent(): void
    {
        $builder = Movie::query();

        RangeFilter::for($builder)->forColumn('year', null, null);

        $this->assertSame('select * from "movies"', $builder->toSql());
        $this->assertSame([], $builder->getBindings());
    }

    public function test_for_value_uses_between_when_both_columns_present(): void
    {
        $builder = Movie::query();

        RangeFilter::for($builder)->forValue(120, 'runtime_min', 'runtime_max');

        $this->assertSame('select * from "movies" where ? between "runtime_min" and "runtime_max"', $builder->toSql());
        $this->assertSame([120], $builder->getBindings());
    }

    public function test_for_value_falls_back_to_lower_bound(): void
    {
        $builder = Movie::query();

        RangeFilter::for($builder)->forValue(95, 'runtime_min', null);

        $this->assertSame('select * from "movies" where "runtime_min" <= ?', $builder->toSql());
        $this->assertSame([95], $builder->getBindings());
    }

    public function test_for_value_falls_back_to_upper_bound(): void
    {
        $builder = Movie::query();

        RangeFilter::for($builder)->forValue(140, null, 'runtime_max');

        $this->assertSame('select * from "movies" where "runtime_max" >= ?', $builder->toSql());
        $this->assertSame([140], $builder->getBindings());
    }

    public function test_for_columns_uses_between_when_bounds_present(): void
    {
        $builder = Movie::query();

        RangeFilter::for($builder)->forColumns('imdb_rating', 'imdb_rating_floor', 'imdb_rating_ceiling');

        $this->assertSame('select * from "movies" where "imdb_rating" between "imdb_rating_floor" and "imdb_rating_ceiling"', $builder->toSql());
        $this->assertSame([], $builder->getBindings());
    }

    public function test_for_columns_handles_lower_column_only(): void
    {
        $builder = Movie::query();

        RangeFilter::for($builder)->forColumns('imdb_rating', 'imdb_rating_floor', null);

        $this->assertSame('select * from "movies" where "imdb_rating" >= "imdb_rating_floor"', $builder->toSql());
        $this->assertSame([], $builder->getBindings());
    }

    public function test_for_columns_handles_upper_column_only(): void
    {
        $builder = Movie::query();

        RangeFilter::for($builder)->forColumns('imdb_rating', null, 'imdb_rating_ceiling');

        $this->assertSame('select * from "movies" where "imdb_rating" <= "imdb_rating_ceiling"', $builder->toSql());
        $this->assertSame([], $builder->getBindings());
    }
}
