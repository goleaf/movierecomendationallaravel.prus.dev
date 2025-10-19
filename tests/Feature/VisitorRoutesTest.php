<?php

namespace Tests\Feature;

use App\Models\Movie;
use Database\Seeders\Testing\FixturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class VisitorRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2024-03-20 12:00:00');
        $this->seed(FixturesSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_home_page_displays_recommendations_and_trending(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertViewHas('recommended', function (Collection $recommended): bool {
                $titles = $recommended->values()->take(3)->pluck('title')->all();
                $this->assertSame(['Time Travelers', 'Indie Darling', 'Neon City'], $titles);

                return true;
            })
            ->assertViewHas('trending', function (Collection $trending): bool {
                $this->assertSame(4, $trending->count());
                $top = $trending->first();
                $this->assertSame('Time Travelers', $top['movie']->title);
                $this->assertSame(5, $top['clicks']);

                return true;
            })
            ->assertSee('Персональные рекомендации')
            ->assertSee('Тренды за 7 дней');
    }

    public function test_trends_page_uses_click_snapshot(): void
    {
        $response = $this->get('/trends');

        $response->assertOk()
            ->assertViewHas('items', function ($items): bool {
                $this->assertInstanceOf(Collection::class, $items);
                $this->assertGreaterThan(0, $items->count());
                $first = $items->first();
                $this->assertSame('Time Travelers', $first->title);
                $this->assertSame(5, $first->clicks);

                return true;
            })
            ->assertViewHas('from', Carbon::now()->subDays(7)->toDateString())
            ->assertSee('Тренды рекомендаций');
    }

    public function test_movie_page_renders_fixture_details(): void
    {
        $movie = Movie::query()->where('title', 'Time Travelers')->firstOrFail();

        $response = $this->get(route('movies.show', $movie));

        $response->assertOk()
            ->assertViewHas('movie', function (Movie $viewMovie) use ($movie): bool {
                $this->assertTrue($movie->is($viewMovie));
                $this->assertSame(['Sci-Fi', 'Adventure'], $viewMovie->genres);
                $this->assertGreaterThan(0, $viewMovie->weighted_score);

                return true;
            })
            ->assertSee($movie->title)
            ->assertSee((string) $movie->weighted_score)
            ->assertSee('Temporal rescue thriller set across collapsing timelines.');
    }
}
