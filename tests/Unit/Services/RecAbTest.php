<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Movie;
use App\Services\RecAb;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecAbTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.redis.client', 'predis');
        config()->set('cache.stores.redis', ['driver' => 'array']);

        if (Schema::hasTable('device_history') && ! Schema::hasColumn('device_history', 'movie_id')) {
            Schema::table('device_history', function (Blueprint $table): void {
                $table->unsignedBigInteger('movie_id')->nullable();
            });
        }

        if (Schema::hasTable('device_history') && ! Schema::hasColumn('device_history', 'placement')) {
            Schema::table('device_history', function (Blueprint $table): void {
                $table->string('placement', 32)->nullable();
            });
        }

        Carbon::setTestNow(CarbonImmutable::parse('2025-01-15 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_for_device_uses_cookie_variant_when_available(): void
    {
        Movie::factory()->count(3)->create();

        config()->set('recs.A', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);
        config()->set('recs.B', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);

        $this->app->instance('request', Request::create('/', 'GET', [], ['ab_variant' => 'A']));
        $serviceA = app(RecAb::class);
        [$variantA, $listA] = $serviceA->forDevice('device-even-2', 2);

        $this->app->instance('request', Request::create('/', 'GET', [], ['ab_variant' => 'B']));
        $serviceB = app(RecAb::class);
        [$variantB, $listB] = $serviceB->forDevice('device-odd', 2);

        $this->assertSame('A', $variantA);
        $this->assertSame('B', $variantB);
        $this->assertInstanceOf(Collection::class, $listA);
        $this->assertCount(2, $listA);
        $this->assertInstanceOf(Collection::class, $listB);
    }

    public function test_for_device_assigns_variant_and_sets_cookie_when_missing(): void
    {
        Movie::factory()->count(2)->create();

        config()->set('recs.ab_split', ['A' => 100.0, 'B' => 0.0]);
        config()->set('recs.A', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);

        $this->app->instance('request', Request::create('/', 'GET'));

        $service = app(RecAb::class);

        [$variant] = $service->forDevice('device-xyz', 1);

        $queued = Cookie::queued('ab_variant');

        $this->assertSame('A', $variant);
        $this->assertNotNull($queued);
        $this->assertSame('A', $queued->getValue());
    }

    public function test_for_device_uses_seed_to_assign_deterministic_variants(): void
    {
        Movie::factory()->count(2)->create();

        config()->set('recs.A', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);
        config()->set('recs.B', ['pop' => 0.7, 'recent' => 0.3, 'pref' => 0.0]);
        config()->set('recs.ab_split', ['A' => 70.0, 'B' => 30.0]);
        config()->set('recs.seed', 'experiment-2025');

        $threshold = 70.0 / (70.0 + 30.0);

        $this->app->instance('request', Request::create('/', 'GET'));
        $service = app(RecAb::class);

        [$firstVariant] = $service->forDevice('device-seeded-1', 1);
        $expectedFirst = $this->expectedVariantForSeed('device-seeded-1', $threshold, 'experiment-2025');

        $this->app->instance('request', Request::create('/', 'GET'));
        [$repeatVariant] = app(RecAb::class)->forDevice('device-seeded-1', 1);

        $this->app->instance('request', Request::create('/', 'GET'));
        [$secondVariant] = app(RecAb::class)->forDevice('device-seeded-2', 1);
        $expectedSecond = $this->expectedVariantForSeed('device-seeded-2', $threshold, 'experiment-2025');

        $this->assertSame($expectedFirst, $firstVariant);
        $this->assertSame($expectedFirst, $repeatVariant);
        $this->assertSame($expectedSecond, $secondVariant);
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

        $this->app->instance('request', Request::create('/', 'GET', [], ['ab_variant' => 'A']));
        $service = app(RecAb::class);

        [$variant, $list] = $service->forDevice('device-even-2', 3);

        $expectedOrder = $movies
            ->mapWithKeys(function (Movie $movie) use ($weights) {
                $popularity = $movie->weighted_score / 10.0;
                $recency = $movie->year
                    ? max(0.0, (5 - (now()->year - (int) $movie->year)) / 5)
                    : 0.0;

                $score = ($weights['pop'] * $popularity) + ($weights['recent'] * $recency);

                return [$movie->id => $score];
            })
            ->sortDesc()
            ->keys()
            ->values()
            ->all();

        $this->assertSame('A', $variant);
        $this->assertSame($expectedOrder, $list->pluck('id')->values()->all());
    }

    public function test_variant_b_applies_device_preference_scores(): void
    {
        $popular = Movie::factory()->create([
            'imdb_tt' => 'tt9000010',
            'title' => 'Galactic Vanguard',
            'imdb_rating' => 8.7,
            'imdb_votes' => 500_000,
            'year' => 2020,
        ]);

        $preferred = Movie::factory()->create([
            'imdb_tt' => 'tt9000011',
            'title' => 'Nebula Drift',
            'imdb_rating' => 6.4,
            'imdb_votes' => 30_000,
            'year' => 2024,
        ]);

        config()->set('recs.A', ['pop' => 0.9, 'recent' => 0.1, 'pref' => 0.0]);
        config()->set('recs.B', ['pop' => 0.2, 'recent' => 0.3, 'pref' => 0.5]);

        DB::table('device_history')->insert([
            'movie_id' => $preferred->id,
            'device_id' => 'device-pref',
            'placement' => 'home',
            'path' => '/variant-b-test',
            'viewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->instance('request', Request::create('/', 'GET', [], ['ab_variant' => 'A']));
        [$variantA, $listA] = app(RecAb::class)->forDevice('device-pref', 2);

        $this->assertSame('A', $variantA);
        $this->assertSame($popular->id, $listA->first()->id, 'Variant A should favour the popular title without preference data.');

        $this->app->instance('request', Request::create('/', 'GET', [], ['ab_variant' => 'B']));
        [$variantB, $listB] = app(RecAb::class)->forDevice('device-pref', 2);

        $this->assertSame('B', $variantB);
        $this->assertSame($preferred->id, $listB->first()->id, 'Variant B should boost the device-preferred title.');
    }

    public function test_weights_are_normalised_before_scoring(): void
    {
        $service = app(RecAb::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('normaliseWeights');
        $method->setAccessible(true);

        /** @var array{pop: float, recent: float, pref: float} $weights */
        $weights = $method->invoke($service, ['pop' => 3, 'recent' => 1, 'pref' => 1]);

        $this->assertEqualsWithDelta(1.0, array_sum($weights), 0.00001);
        $this->assertEqualsWithDelta(0.6, $weights['pop'], 0.00001);
        $this->assertEqualsWithDelta(0.2, $weights['recent'], 0.00001);
        $this->assertEqualsWithDelta(0.2, $weights['pref'], 0.00001);

        /** @var array{pop: float, recent: float, pref: float} $fallback */
        $fallback = $method->invoke($service, ['pop' => 0, 'recent' => 0, 'pref' => 0]);

        $this->assertEqualsWithDelta(1.0, array_sum($fallback), 0.00001);
        $this->assertEqualsWithDelta(0.5, $fallback['pop'], 0.00001);
        $this->assertEqualsWithDelta(0.2, $fallback['recent'], 0.00001);
        $this->assertEqualsWithDelta(0.3, $fallback['pref'], 0.00001);
    }

    private function expectedVariantForSeed(string $deviceId, float $threshold, string $seed): string
    {
        $hash = crc32($seed.'|'.$deviceId);
        $unsigned = (int) sprintf('%u', $hash);
        $random = $unsigned / 4294967295;

        return $random < $threshold ? 'A' : 'B';
    }
}
