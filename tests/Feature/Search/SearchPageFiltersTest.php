<?php

declare(strict_types=1);

namespace {
    if (! function_exists('csp_nonce')) {
        function csp_nonce(): string
        {
            return '';
        }
    }
}

namespace Tests\Feature\Search {

    use App\Models\Movie;
    use Illuminate\Foundation\Testing\RefreshDatabase;
    use Spatie\Csp\AddCspHeaders;
    use Tests\Seeders\MovieCatalogSeeder;
    use Tests\TestCase;

    class SearchPageFiltersTest extends TestCase
    {
        use RefreshDatabase;

        protected function setUp(): void
        {
            parent::setUp();

            $this->withoutMiddleware(AddCspHeaders::class);
        }

        public function test_page_response_filters_items_by_selected_type_and_year(): void
        {
            $this->seed(MovieCatalogSeeder::class);

            $matchingMovie = Movie::factory()->movie()->create([
                'title' => 'Точечный поиск',
                'genres' => ['science fiction', 'thriller'],
                'year' => 2023,
            ]);

            Movie::factory()->series()->create([
                'title' => 'Лишняя серия',
                'genres' => ['science fiction'],
                'year' => 2023,
            ]);

            Movie::factory()->movie()->create([
                'title' => 'Старый результат',
                'genres' => ['science fiction'],
                'year' => 2010,
            ]);

            $response = $this->get(route('search', [
                'type' => 'movie',
                'genre' => 'science fiction',
                'yf' => 2022,
                'yt' => 2024,
            ]));

            $response->assertOk();
            $response->assertViewHas('items', function ($items) use ($matchingMovie) {
                return $items->count() === 1 && $items->first()->is($matchingMovie);
            });

            $response->assertViewHasAll([
                'type' => 'movie',
                'genre' => 'science fiction',
                'yf' => 2022,
                'yt' => 2024,
            ]);
        }
    }

}
