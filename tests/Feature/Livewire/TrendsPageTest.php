<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\TrendsPage;
use App\Models\Movie;
use App\Services\Analytics\TrendsRollupService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

use function image_proxy_url;

class TrendsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_proxied_poster_urls(): void
    {
        $now = CarbonImmutable::parse('2025-01-10 12:00:00');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);

        $poster = 'https://img.example.com/posters/trending-proxy.jpg';

        Movie::factory()->movie()->create([
            'title' => 'Trending Proxy Hit',
            'poster_url' => $poster,
            'imdb_votes' => 25000,
            'imdb_rating' => 8.7,
        ]);

        $rollup = Mockery::mock(TrendsRollupService::class);
        $rollup->shouldReceive('ensureBackfill')->andReturnNull();
        $this->app->instance(TrendsRollupService::class, $rollup);

        Livewire::test(TrendsPage::class)
            ->assertSee(image_proxy_url($poster));

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        Mockery::close();
    }
}
