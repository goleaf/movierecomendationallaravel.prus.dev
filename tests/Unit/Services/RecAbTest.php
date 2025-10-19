<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Movie;
use App\Services\RecAb;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RecAbTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse('2025-01-15 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_for_device_chooses_variant_based_on_crc32_parity(): void
    {
        Movie::factory()->count(3)->create();

        config()->set('recs.A', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);
        config()->set('recs.B', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);

        $service = app(RecAb::class);

        [$variantA, $listA] = $service->forDevice('device-even-2', 2);
        [$variantB, $listB] = $service->forDevice('device-odd', 2);

        $this->assertSame('A', $variantA);
        $this->assertSame('B', $variantB);
        $this->assertInstanceOf(Collection::class, $listA);
        $this->assertCount(2, $listA);
        $this->assertInstanceOf(Collection::class, $listB);
    }

    public function test_for_device_ranks_movies_using_weighted_popularity_and_recency(): void
    {
        $movies = collect([
            Movie::factory()->create([
                'imdb_tt' => 'tt9000001',
                'title' => 'Alpha Frontier',
                'imdb_rating' => 9.1,
                'imdb_votes' => 150_000,
                'year' => 2023,
            ]),
            Movie::factory()->create([
                'imdb_tt' => 'tt9000002',
                'title' => 'Beacon Rising',
                'imdb_rating' => 8.3,
                'imdb_votes' => 65_000,
                'year' => 2025,
            ]),
            Movie::factory()->create([
                'imdb_tt' => 'tt9000003',
                'title' => 'Cosmic Ashes',
                'imdb_rating' => 7.8,
                'imdb_votes' => 210_000,
                'year' => 2019,
            ]),
        ]);

        $weights = ['pop' => 0.6, 'recent' => 0.4, 'pref' => 0.0];
        config()->set('recs.A', $weights);
        config()->set('recs.B', $weights);

        $service = app(RecAb::class);

        [$variant, $list] = $service->forDevice('device-even-2', 3);

        $expectedOrder = $movies
            ->mapWithKeys(function (Movie $movie) use ($weights) {
                $popularity = ((float) ($movie->imdb_rating ?? 0)) / 10
                    * (max(0.0, (float) log10(($movie->imdb_votes ?? 0) + 1)) / 6);
                $recency = $movie->year
                    ? max(0.0, (5 - (now()->year - (int) $movie->year)) / 5)
                    : 0.0;

                $score = $weights['pop'] * $popularity + $weights['recent'] * $recency;

                return [$movie->id => $score];
            })
            ->sortDesc()
            ->keys()
            ->values()
            ->all();

        $this->assertSame('A', $variant);
        $this->assertSame($expectedOrder, $list->pluck('id')->values()->all());
    }
}
