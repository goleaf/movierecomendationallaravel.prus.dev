<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Movie;
use App\Services\RecAb;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $service = new RecAb;

        [$variantA, $listA] = $service->forDevice('dev4', 3);
        [$variantB, $listB] = $service->forDevice('dev1', 3);

        CarbonImmutable::setTestNow();

        $this->assertSame('A', $variantA);
        $this->assertSame('B', $variantB);

        $this->assertSame('Old Hit', $listA->first()?->title);
        $this->assertSame('New Release', $listB->first()?->title);
        $this->assertNotSame($listA->first()?->id, $listB->first()?->id);
    }
}
