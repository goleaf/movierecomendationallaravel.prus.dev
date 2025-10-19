<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Movie;
use App\Services\RecAb;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RecAbTest extends TestCase
{
    use RefreshDatabase;

    public function test_variants_produce_different_ordering(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::create(2024, 1, 1));

        Movie::query()->create([
            'imdb_tt' => 'tt0001',
            'title' => 'Old Hit',
            'type' => 'movie',
            'year' => 1994,
            'imdb_rating' => 9.5,
            'imdb_votes' => 1000000,
        ]);

        Movie::query()->create([
            'imdb_tt' => 'tt0002',
            'title' => 'New Release',
            'type' => 'movie',
            'year' => 2024,
            'imdb_rating' => 7.0,
            'imdb_votes' => 1000,
        ]);

        Movie::query()->create([
            'imdb_tt' => 'tt0003',
            'title' => 'Steady Favorite',
            'type' => 'movie',
            'year' => 2018,
            'imdb_rating' => 8.0,
            'imdb_votes' => 50000,
        ]);

        $this->storeRecommendationWeights([
            'A' => ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0],
            'B' => ['pop' => 0.2, 'recent' => 0.8, 'pref' => 0.0],
        ]);

        $this->app->instance('request', Request::create('/', 'GET', [], ['ab_variant' => 'A']));
        $serviceA = app(RecAb::class);
        [$variantA, $listA] = $serviceA->forDevice('dev4', 3);

        $this->app->instance('request', Request::create('/', 'GET', [], ['ab_variant' => 'B']));
        $serviceB = app(RecAb::class);
        [$variantB, $listB] = $serviceB->forDevice('dev1', 3);

        CarbonImmutable::setTestNow();

        $this->assertSame('A', $variantA);
        $this->assertSame('B', $variantB);

        $this->assertSame('Old Hit', $listA->first()?->title);
        $this->assertSame('New Release', $listB->first()?->title);
        $this->assertNotSame($listA->first()?->id, $listB->first()?->id);
    }
}
