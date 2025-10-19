<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\MovieIndexRequest;
use App\Support\MovieIndexFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MovieIndexParamsTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_year_boundaries(): void
    {
        $filters = $this->filtersFor([
            'year_from' => 1870,
            'year_to' => 2100,
        ]);

        $this->assertSame(1870, $filters->yearFrom);
        $this->assertSame(2100, $filters->yearTo);
    }

    public function test_discards_out_of_range_years(): void
    {
        $filters = $this->filtersFor([
            'year_from' => 1869,
            'year_to' => 2101,
        ]);

        $this->assertNull($filters->yearFrom);
        $this->assertNull($filters->yearTo);
    }

    public function test_handles_empty_genres_array(): void
    {
        $filters = $this->filtersFor([
            'genres' => [],
        ]);

        $this->assertSame([], $filters->genres);
    }

    public function test_canonicalizes_genres_noise(): void
    {
        $filters = $this->filtersFor([
            'genres' => [' Sci-Fi ', 'drama', 'sci fi', 'Drama', ' ', null, 123],
        ]);

        $this->assertSame(['drama', 'sci-fi'], $filters->genres);
    }

    public function test_defaults_unknown_sort_to_popular(): void
    {
        $filters = $this->filtersFor([
            'sort' => 'unexpected',
        ]);

        $this->assertSame(MovieIndexFilters::DEFAULT_SORT, $filters->sort);
    }

    private function filtersFor(array $query): MovieIndexFilters
    {
        /** @var MovieIndexRequest $request */
        $request = MovieIndexRequest::create('/movies', 'GET', $query);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        return $request->filters();
    }
}
