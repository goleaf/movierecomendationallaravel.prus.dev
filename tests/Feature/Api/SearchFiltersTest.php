<?php

namespace Tests\Feature\Api;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Csp\AddCspHeaders;
use Tests\Seeders\MovieCatalogSeeder;
use Tests\TestCase;

use function image_proxy_url;

class SearchFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(AddCspHeaders::class);
    }

    public function test_it_filters_search_results_by_type_genre_and_year(): void
    {
        $this->seed(MovieCatalogSeeder::class);

        $matching = Movie::factory()->movie()->create([
            'title' => 'Подходящий фильм',
            'year' => 2020,
            'genres' => ['drama', 'comedy'],
        ]);

        Movie::factory()->series()->create([
            'title' => 'Лишний сериал',
            'year' => 2022,
            'genres' => ['drama'],
        ]);

        Movie::factory()->movie()->create([
            'title' => 'Чужой жанр',
            'year' => 2022,
            'genres' => ['action'],
        ]);

        Movie::factory()->movie()->create([
            'title' => 'Старый фильм',
            'year' => 2010,
            'genres' => ['drama'],
        ]);

        $response = $this->get('/search?type=movie&genre=drama&yf=2019&yt=2021', [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $items = collect($response->json('data'));
        $this->assertNotEmpty($items);
        $this->assertTrue($items->every(fn (array $item): bool => $item['type'] === 'movie'));
        $this->assertTrue($items->every(fn (array $item): bool => in_array('drama', $item['genres'], true)));
        $this->assertTrue($items->every(fn (array $item): bool => $item['year'] >= 2019 && $item['year'] <= 2021));
        $this->assertTrue($items->pluck('id')->contains($matching->id));
    }

    public function test_it_handles_inverted_year_filters(): void
    {
        $this->seed(MovieCatalogSeeder::class);

        $matching = Movie::factory()->movie()->create([
            'title' => 'Историческая драма',
            'year' => 2005,
            'genres' => ['drama'],
        ]);

        Movie::factory()->movie()->create([
            'title' => 'Вне диапазона',
            'year' => 1995,
            'genres' => ['drama'],
        ]);

        $response = $this->get('/search?type=movie&genre=drama&yf=2010&yt=2000', [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $items = collect($response->json('data'));
        $this->assertNotEmpty($items);
        $this->assertTrue($items->every(fn (array $item): bool => $item['type'] === 'movie'));
        $this->assertTrue($items->every(fn (array $item): bool => in_array('drama', $item['genres'], true)));
        $this->assertTrue($items->every(fn (array $item): bool => $item['year'] >= 2000 && $item['year'] <= 2010));
        $this->assertTrue($items->pluck('id')->contains($matching->id));
    }

    public function test_it_supports_animation_type_filter(): void
    {
        $this->seed(MovieCatalogSeeder::class);

        $matching = Movie::factory()->animation()->create([
            'title' => 'Город Светлячков',
            'year' => 2019,
            'genres' => ['animation', 'family'],
        ]);

        Movie::factory()->movie()->create([
            'title' => 'Игровое кино',
            'year' => 2019,
            'genres' => ['family'],
        ]);

        $response = $this->get('/search?type=animation&genre=animation&yf=2018&yt=2020', [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $items = collect($response->json('data'));
        $this->assertNotEmpty($items);
        $this->assertTrue($items->every(fn (array $item): bool => $item['type'] === 'animation'));
        $this->assertTrue($items->every(fn (array $item): bool => in_array('animation', $item['genres'], true)));
        $this->assertTrue($items->every(fn (array $item): bool => $item['year'] >= 2018 && $item['year'] <= 2020));
        $this->assertTrue($items->pluck('id')->contains($matching->id));
    }

    public function test_search_results_include_proxied_poster_urls(): void
    {
        $now = Carbon::parse('2025-01-10 12:00:00');
        Carbon::setTestNow($now);

        $poster = 'https://img.example.com/posters/proxy-search.jpg';

        $movie = Movie::factory()->movie()->create([
            'title' => 'Proxy Search Showcase',
            'poster_url' => $poster,
            'genres' => ['drama'],
        ]);

        $response = $this->get('/search?q=Proxy&per=10', [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $response->assertJson(fn ($json) => $json
            ->where('data.0.id', $movie->id)
            ->where('data.0.poster_url', image_proxy_url($poster))
        );

        Carbon::setTestNow();
    }
}
