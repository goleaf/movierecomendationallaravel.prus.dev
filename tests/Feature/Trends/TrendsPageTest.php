<?php

declare(strict_types=1);

namespace Tests\Feature\Trends;

use App\Livewire\TrendsPage;
use App\Models\Movie;
use App\Services\Analytics\TrendsRollupService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\Seeders\MovieCatalogSeeder;
use Tests\TestCase;

class TrendsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Date::use(Carbon::class);
        Mockery::close();

        parent::tearDown();
    }

    public function test_trending_items_sorted_by_clicks_when_rollups_present(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2025-02-08 12:00:00'));
        Date::use(CarbonImmutable::class);
        $this->seed(MovieCatalogSeeder::class);

        $mock = Mockery::mock(TrendsRollupService::class);
        $mock->shouldReceive('ensureBackfill')->andReturnNull();
        app()->instance(TrendsRollupService::class, $mock);

        $movieA = Movie::factory()->movie()->create([
            'title' => 'Синяя орбита',
            'imdb_votes' => 120_000,
            'imdb_rating' => 8.4,
        ]);
        $movieB = Movie::factory()->movie()->create([
            'title' => 'Красный шторм',
            'imdb_votes' => 80_000,
            'imdb_rating' => 8.1,
        ]);
        $movieC = Movie::factory()->movie()->create([
            'title' => 'Зелёный фронтир',
            'imdb_votes' => 65_000,
            'imdb_rating' => 8.0,
        ]);

        $baseDay = CarbonImmutable::now('UTC')->subDays(6);
        DB::table('rec_trending_rollups')->insert([
            [
                'movie_id' => $movieA->id,
                'captured_on' => $baseDay->toDateString(),
                'clicks' => 12,
                'created_at' => $baseDay,
                'updated_at' => $baseDay,
            ],
            [
                'movie_id' => $movieA->id,
                'captured_on' => $baseDay->addDays(2)->toDateString(),
                'clicks' => 5,
                'created_at' => $baseDay->addDays(2),
                'updated_at' => $baseDay->addDays(2),
            ],
            [
                'movie_id' => $movieB->id,
                'captured_on' => $baseDay->addDay()->toDateString(),
                'clicks' => 14,
                'created_at' => $baseDay->addDay(),
                'updated_at' => $baseDay->addDay(),
            ],
            [
                'movie_id' => $movieC->id,
                'captured_on' => $baseDay->addDays(3)->toDateString(),
                'clicks' => 7,
                'created_at' => $baseDay->addDays(3),
                'updated_at' => $baseDay->addDays(3),
            ],
        ]);

        $component = Livewire::test(TrendsPage::class, [
            'days' => 7,
        ]);

        $items = $component->get('items');
        $clicks = $items->pluck('clicks')->all();
        $this->assertNotEmpty($clicks);
        $this->assertSame($clicks, array_values($clicks));
        $this->assertTrue($this->isSortedDescending($clicks));
        $this->assertTrue($items->pluck('id')->contains($movieA->id));
        $this->assertTrue($items->pluck('id')->contains($movieB->id));
        $this->assertTrue($items->pluck('id')->contains($movieC->id));

        $component->assertSet('from', CarbonImmutable::now('UTC')->subDays(7)->toDateString());
        $component->assertSet('to', CarbonImmutable::now('UTC')->toDateString());
    }

    public function test_fallback_order_matches_weighted_score_when_no_clicks_available(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2025-04-15 08:00:00'));
        Date::use(CarbonImmutable::class);

        $movieTop = Movie::factory()->movie()->create([
            'title' => 'Пик популярности',
            'imdb_rating' => 8.7,
            'imdb_votes' => 540_000,
        ]);
        $movieMid = Movie::factory()->movie()->create([
            'title' => 'Средний герой',
            'imdb_rating' => 8.3,
            'imdb_votes' => 260_000,
        ]);
        $movieLow = Movie::factory()->movie()->create([
            'title' => 'Артхаус',
            'imdb_rating' => 8.9,
            'imdb_votes' => 52_000,
        ]);

        $mock = Mockery::mock(TrendsRollupService::class);
        $mock->shouldReceive('ensureBackfill')->andReturnNull();
        app()->instance(TrendsRollupService::class, $mock);

        $component = Livewire::test(TrendsPage::class, [
            'days' => 7,
            'type' => 'movie',
        ]);

        $items = $component->get('items');
        $this->assertCount(3, $items);

        $votes = $items->map(fn (array $row) => $row['imdb_votes'])->all();
        $this->assertTrue($this->isSortedDescending($votes));

        $grouped = $items->groupBy('imdb_votes');
        foreach ($grouped as $entries) {
            $ratings = $entries->map(fn (array $row) => $row['imdb_rating'])->all();
            $this->assertTrue($this->isSortedDescending($ratings));
        }
        $this->assertTrue($items->pluck('id')->contains($movieTop->id));
        $this->assertTrue($items->pluck('id')->contains($movieMid->id));
        $this->assertTrue($items->pluck('id')->contains($movieLow->id));

        $expectedVotes = collect([$movieTop, $movieMid, $movieLow])
            ->map(fn (Movie $movie) => $movie->imdb_votes)
            ->all();

        $this->assertTrue($this->isSortedDescending($expectedVotes));
    }

    /**
     * @param  array<int, float|int|null>  $values
     */
    private function isSortedDescending(array $values): bool
    {
        $filtered = array_values(array_map(fn ($value) => (float) ($value ?? 0), $values));

        for ($i = 1, $count = count($filtered); $i < $count; $i++) {
            if ($filtered[$i] > $filtered[$i - 1]) {
                return false;
            }
        }

        return true;
    }
}
