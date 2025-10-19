<?php

namespace Tests\Unit;

use App\Models\Movie;
use App\Services\RecAb;
use App\Services\RecommendationLogger;
use App\Services\Recommender;
use Database\Seeders\Testing\FixturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
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
        Mockery::close();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_variant_is_determined_by_crc32_parity(): void
    {
        $service = app(RecAb::class);

        [$variantEven] = $service->forDevice('device-even-2', 3);
        [$variantOdd] = $service->forDevice('device-odd', 3);

        $this->assertSame('A', $variantEven);
        $this->assertSame('B', $variantOdd);
    }

    public function test_rec_ab_prioritises_recent_highly_rated_movies(): void
    {
        $service = app(RecAb::class);

        [$variant, $list] = $service->forDevice('device-even-2', 5);

        $this->assertSame('A', $variant);
        $this->assertInstanceOf(Collection::class, $list);
        $this->assertSame(
            ['Time Travelers', 'Indie Darling', 'Neon City'],
            $list->values()->take(3)->pluck('title')->all()
        );
    }

    public function test_recommender_returns_ab_rankings(): void
    {
        $movies = Movie::query()->whereIn('title', ['Time Travelers', 'Indie Darling'])->get();
        $expected = $movies->values();

        $ab = Mockery::mock(RecAb::class);
        $ab->shouldReceive('forDevice')
            ->once()
            ->with('device-fixture', 2)
            ->andReturn(['B', $expected]);

        $logger = Mockery::mock(RecommendationLogger::class);
        $logger->shouldReceive('recordRecommendation')
            ->once()
            ->with('device-fixture', 'B', 'home', $expected);

        $recommender = new Recommender($ab, $logger);

        $result = $recommender->recommendForDevice('device-fixture', 2);

        $this->assertSame('B', $result->variant());
        $this->assertSame($expected->pluck('title')->all(), $result->movies()->pluck('title')->all());
    }
}
