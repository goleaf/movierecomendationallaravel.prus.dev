<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Queries\MovieSearchQuery;
use App\Support\MovieSearchFilters;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class SqlSnapshotTest extends TestCase
{
    public function test_movie_search_range_snapshots(): void
    {
        $scenarios = [
            'closed' => new MovieSearchFilters(
                query: '',
                type: 'movie',
                genre: 'science fiction',
                yearFrom: 1995,
                yearTo: 2005,
                runtime: ['min' => 90, 'max' => 140],
                rating: ['min' => 7.5, 'max' => 9.1],
            ),
            'open-lower' => new MovieSearchFilters(
                query: '',
                type: null,
                genre: null,
                yearFrom: 2010,
                yearTo: null,
                runtime: ['min' => 100, 'max' => null],
                rating: ['min' => null, 'max' => 8.4],
            ),
            'open-upper' => new MovieSearchFilters(
                query: '',
                type: null,
                genre: null,
                yearFrom: null,
                yearTo: 2012,
                runtime: ['min' => null, 'max' => 110],
                rating: ['min' => 6.8, 'max' => null],
            ),
            'no-bounds' => new MovieSearchFilters(
                query: '',
                type: null,
                genre: null,
                yearFrom: null,
                yearTo: null,
            ),
        ];

        $statements = [];

        foreach ($scenarios as $label => $filters) {
            $builder = MovieSearchQuery::forFilters($filters);

            $statements[] = sprintf('-- %s', $label);
            $statements[] = $this->statementFromBuilder($builder);
        }

        $this->assertSnapshotEquals(
            'tests/Feature/__snapshots__/movie_search_range_filters.sql',
            implode("\n", $statements)."\n"
        );
    }

    private function statementFromBuilder(Builder $builder): string
    {
        $sql = $builder->toSql();
        $bindings = array_map(fn ($value): string => $this->formatBinding($value), $builder->getBindings());

        return Str::replaceArray('?', $bindings, $sql);
    }

    private function assertSnapshotEquals(string $path, string $actual): void
    {
        $fullPath = base_path($path);

        if (! File::exists($fullPath)) {
            File::ensureDirectoryExists(dirname($fullPath));
            File::put($fullPath, $this->normalize($actual)."\n");
        }

        $expected = File::get($fullPath);

        $this->assertSame(
            $this->normalize($expected),
            $this->normalize($actual),
            sprintf('Snapshot mismatch for %s', $path)
        );
    }

    private function normalize(string $value): string
    {
        return trim(str_replace("\r\n", "\n", $value));
    }

    private function formatBinding(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return "'".$value->format('Y-m-d H:i:s')."'";
        }

        if (is_string($value)) {
            return "'".str_replace("'", "''", $value)."'";
        }

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
