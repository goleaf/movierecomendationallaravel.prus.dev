<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Movie;
use App\Services\RecAb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithRecommendationWeightsSettings;
use Tests\TestCase;

class RecAbFormulaTest extends TestCase
{
    use InteractsWithRecommendationWeightsSettings;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_scoring_combines_weighted_score_and_recency(): void
    {
        Carbon::setTestNow(now()->setDate(2025, 1, 1));

        $this->updateRecommendationWeightsSettings([
            'ab_split' => ['A' => 100.0, 'B' => 0.0],
            'variant_a' => ['pop' => 0.6, 'recent' => 0.4, 'pref' => 0.0],
            'seed' => 'unit-tests',
        ]);

        $recent = Movie::factory()->movie()->create([
            'title' => 'Свежий хит',
            'year' => 2025,
            'imdb_rating' => 8.5,
            'imdb_votes' => 80_000,
        ]);
        $popular = Movie::factory()->movie()->create([
            'title' => 'Популярный фильм',
            'year' => 2019,
            'imdb_rating' => 8.3,
            'imdb_votes' => 320_000,
        ]);
        $niche = Movie::factory()->movie()->create([
            'title' => 'Нишевое кино',
            'year' => 2017,
            'imdb_rating' => 8.9,
            'imdb_votes' => 45_000,
        ]);

        [$variant, $list] = app(RecAb::class)->forDevice('device-123', 3);

        $this->assertSame('A', $variant);
        $orderedIds = $list->pluck('id')->all();

        $scores = $list->mapWithKeys(function (Movie $movie): array {
            $popularity = $movie->weighted_score / 10.0;
            $currentYear = now()->year;
            $recency = $movie->year
                ? max(0.0, (5 - ($currentYear - (int) $movie->year))) / 5.0
                : 0.0;

            $score = (0.6 * $popularity) + (0.4 * $recency);

            return [$movie->id => round($score, 4)];
        });

        $expectedOrder = $scores->sortDesc()->keys()->values()->all();

        $this->assertSame($expectedOrder, $orderedIds);
        $this->assertTrue($scores->values()->sortDesc()->values()->all() === $scores->values()->all());
    }
}
