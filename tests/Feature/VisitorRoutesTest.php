<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\Seeders\DemoContentSeeder;
use Tests\TestCase;

class VisitorRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse('2025-01-15 12:00:00'));

        $this->seed(DemoContentSeeder::class);

        config()->set('recs.A', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);
        config()->set('recs.B', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_home_page_displays_seeded_recommendations_and_trending(): void
    {
        $response = $this->withCookie('did', 'device-even-2')->get('/');

        $response->assertOk();
        $response->assertViewIs('home.index');

        $response->assertViewHas('recommended', function (Collection $movies): bool {
            return $movies->count() === 3
                && $movies->pluck('title')->toArray() === [
                    'The Quantum Enigma',
                    'Solaris Rising',
                    'Nebula Drift',
                ];
        });

        $response->assertViewHas('trending', function (Collection $rows): bool {
            if ($rows->count() !== 3) {
                return false;
            }

            $first = $rows->first();

            return $first['movie']->title === 'The Quantum Enigma'
                && $first['clicks'] === 3;
        });

        $response->assertSeeText('Персональные рекомендации');
        $response->assertSeeText('Клики: 3');
    }

    public function test_trends_page_lists_click_metrics_from_seeded_snapshot(): void
    {
        $response = $this->get('/trends');

        $response->assertOk();
        $response->assertViewIs('trends.index');
        $response->assertViewHas('days', 7);

        $response->assertViewHas('items', function ($items): bool {
            $titles = collect($items)->pluck('title')->toArray();

            return $titles === [
                'The Quantum Enigma',
                'Solaris Rising',
                'Nebula Drift',
            ];
        });

        $response->assertSeeText('Клики: 3');
        $response->assertSeeText('Клики: 2');
    }

    public function test_movie_page_shows_seeded_movie_details(): void
    {
        $response = $this->get('/movies/1');

        $response->assertOk();
        $response->assertViewIs('movies.show');
        $response->assertViewHas('movie', fn ($movie) => $movie->title === 'The Quantum Enigma');

        $response->assertSeeText('The Quantum Enigma');
        $response->assertSeeText('IMDb 8.8');
        $response->assertSeeText('Weighted');
    }
}
