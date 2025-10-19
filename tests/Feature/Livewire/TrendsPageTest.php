<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\TrendsPage as TrendsComponent;
use App\Models\Movie;
use App\Services\Analytics\TrendsRollupService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TrendsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('database.redis.client', 'predis');
        config()->set('cache.stores.redis', ['driver' => 'array']);

        $this->withoutMiddleware(\Spatie\Csp\AddCspHeaders::class);

        app()->instance(TrendsRollupService::class, \Mockery::mock(TrendsRollupService::class, function ($mock): void {
            $mock->shouldReceive('ensureBackfill')->andReturnNull();
        }));

        Carbon::setTestNow(CarbonImmutable::parse('2025-01-15 12:00:00'));

        \Illuminate\Support\Facades\Date::use(CarbonImmutable::class);

        Movie::factory()->create([
            'title' => 'Proxy Poster Film',
            'poster_url' => 'https://example.com/posters/proxy-film.jpg',
            'imdb_rating' => 8.5,
            'imdb_votes' => 150_000,
        ]);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Date::use(Carbon::class);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_trends_component_renders_proxied_posters(): void
    {
        Livewire::test(TrendsComponent::class)
            ->assertOk()
            ->assertSee('proxy/image?type=poster', false)
            ->assertDontSee('https://example.com/posters/proxy-film.jpg');
    }
}
