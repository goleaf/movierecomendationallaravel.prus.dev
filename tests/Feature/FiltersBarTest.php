<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FiltersBarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::get('/test-filters', function () {
                return view('components.filters-bar');
            })->name('test.filters');

            Route::get('/movies/{movie}/filters-demo', function () {
                return view('components.filters-bar');
            })->name('test.movie-filters');
        });
    }

    public function test_it_reads_query_parameters_and_builds_links(): void
    {
        $response = $this->get('/test-filters?genre=comedy&year=2021');

        $response->assertOk();
        $response->assertSee('Genre: comedy', false);
        $response->assertSee('Year: 2021', false);
        $response->assertSee('href="http://localhost/search?genre=comedy&amp;year=2021&amp;type=movie"', false);
        $response->assertSee('href="http://localhost/test-filters?genre=comedy&amp;year=2021&amp;type=series"', false);
    }

    public function test_it_reads_route_parameters_when_present(): void
    {
        $response = $this->get('/movies/42/filters-demo');

        $response->assertOk();
        $response->assertSee('Movie from route parameter: 42', false);
    }
}
