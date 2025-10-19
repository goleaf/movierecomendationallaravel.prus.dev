<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Services\RecAb;
use App\Services\Recommender;
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

        Carbon::setTestNow('2025-01-01 12:00:00');

        config()->set('recs.A', [
            'pop' => 0.1,
            'recent' => 0.9,
            'pref' => 0.0,
        ]);

        config()->set('recs.B', [
            'pop' => 0.9,
            'recent' => 0.1,
            'pref' => 0.0,
        ]);

        Movie::query()->create([
            'imdb_tt' => 'tt001',
            'title' => 'Classic Blockbuster',
            'type' => 'movie',
            'year' => 1995,
            'imdb_rating' => 9.1,
            'imdb_votes' => 1200000,
        ]);

        Movie::query()->create([
            'imdb_tt' => 'tt002',
            'title' => 'Fresh Indie Hit',
            'type' => 'movie',
            'year' => 2025,
            'imdb_rating' => 6.2,
            'imdb_votes' => 300,
        ]);

        Movie::query()->create([
            'imdb_tt' => 'tt003',
            'title' => 'Balanced Crowd Pleaser',
            'type' => 'movie',
            'year' => 2023,
            'imdb_rating' => 7.5,
            'imdb_votes' => 45000,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => 'database/migrations/2025_02_14_000100_create_movies_table.php',
        ];
    }

    public function test_variant_specific_weights_change_recommendation_order(): void
    {
        $service = app(RecAb::class);

        [$variantA, $listA] = $service->forDevice('even-device', 3);
        [$variantB, $listB] = $service->forDevice('odd-device', 3);

        $this->assertSame('A', $variantA);
        $this->assertSame('B', $variantB);

        $this->assertSame('Fresh Indie Hit', $listA->first()->title);
        $this->assertSame('Classic Blockbuster', $listB->first()->title);
        $this->assertNotSame($listA->pluck('id')->all(), $listB->pluck('id')->all());
    }

    public function test_recommender_returns_variant_and_recommendations(): void
    {
        $recommender = app(Recommender::class);

        $result = $recommender->recommendForDevice('even-device', 3);

        $this->assertSame('A', $result['variant']);
        $this->assertInstanceOf(Collection::class, $result['recommendations']);
        $this->assertSame('Fresh Indie Hit', $result['recommendations']->first()->title);
    }
}
