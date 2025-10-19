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

namespace Tests\Feature {

    use App\Livewire\HomePage;
    use App\Livewire\TrendsPage;
    use App\Services\Analytics\TrendsRollupService;
    use Carbon\CarbonImmutable;
    use Illuminate\Foundation\Testing\RefreshDatabase;
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\Date;
    use Livewire\Livewire;
    use Tests\Seeders\DemoContentSeeder;
    use Tests\TestCase;

    class VisitorRoutesTest extends TestCase
    {
        use RefreshDatabase;

        protected function setUp(): void
        {
            parent::setUp();

            Carbon::setTestNow(CarbonImmutable::parse('2025-01-15 12:00:00'));
            Date::use(CarbonImmutable::class);

            $this->mock(TrendsRollupService::class, function ($mock): void {
                $mock->shouldIgnoreMissing();
                $mock->shouldReceive('ensureBackfill')->andReturnNull();
            });

            $this->seed(DemoContentSeeder::class);

            config()->set('recs.A', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);
            config()->set('recs.B', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);
        }

        protected function tearDown(): void
        {
            Carbon::setTestNow();
            Date::useDefault();

            parent::tearDown();
        }

        public function test_home_page_displays_seeded_recommendations_and_trending(): void
        {
            $response = $this->withCookie('did', 'device-even-2')->get('/');

            $response->assertOk();

            $posterProxy = artwork_url('https://example.com/posters/quantum.jpg');
            $this->assertNotNull($posterProxy);
            $encodedPoster = rawurlencode('https://example.com/posters/quantum.jpg');

            Livewire::test(HomePage::class)
                ->assertSee('/api/artwork')
                ->assertSee('src='.$encodedPoster)
                ->assertSee('Персональные рекомендации')
                ->assertSee('Клики: 3')
                ->assertSee('The Quantum Enigma')
                ->assertSee('Solaris Rising')
                ->assertSee('Nebula Drift');
        }

        public function test_trends_page_lists_click_metrics_from_seeded_snapshot(): void
        {
            $response = $this->get('/trends');

            $response->assertOk();

            $posterProxy = artwork_url('https://example.com/posters/quantum.jpg');
            $this->assertNotNull($posterProxy);
            $encodedPoster = rawurlencode('https://example.com/posters/quantum.jpg');

            Livewire::test(TrendsPage::class)
                ->assertSee('/api/artwork')
                ->assertSee('src='.$encodedPoster)
                ->assertSee('Клики: 3')
                ->assertSee('Клики: 2')
                ->assertSee('The Quantum Enigma')
                ->assertSee('Solaris Rising')
                ->assertSee('Nebula Drift');
        }

        public function test_movie_page_shows_seeded_movie_details(): void
        {
            $response = $this->get('/movies/1');

            $response->assertOk();
            $response->assertViewIs('movies.show');
            $response->assertViewHas('movie', fn ($movie) => $movie->title === 'The Quantum Enigma');

            $posterProxy = artwork_url('https://example.com/posters/quantum.jpg');
            $this->assertNotNull($posterProxy);
            $encodedPoster = rawurlencode('https://example.com/posters/quantum.jpg');
            $response->assertSee('/api/artwork');
            $response->assertSee('src='.$encodedPoster);

            $response->assertSeeText('The Quantum Enigma');
            $response->assertSeeText('IMDb 8.8');
            $response->assertSeeText('Weighted');
        }

        public function test_search_page_renders_proxied_poster_urls(): void
        {
            $response = $this->get('/search?q=quantum');

            $response->assertOk();
            $response->assertViewIs('search.index');

            $posterProxy = artwork_url('https://example.com/posters/quantum.jpg');
            $this->assertNotNull($posterProxy);
            $encodedPoster = rawurlencode('https://example.com/posters/quantum.jpg');
            $response->assertSee('/api/artwork');
            $response->assertSee('src='.$encodedPoster);
        }
    }

}
