<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

class RecAb
{
    private const COOKIE_NAME = 'ab_variant';

    private const COOKIE_LIFETIME_MINUTES = 60 * 24 * 365 * 5;

    public function __construct(
        private readonly RecommendationWeightsResolver $weights,
        private readonly DevicePreferenceService $preferences,
    ) {
    }

    /**
     * @return array{0:string,1:Collection<int,Movie>}
     */
    public function forDevice(string $deviceId, int $limit = 12): array
    {
        $variant = $this->resolveVariant();
        $list = $this->score($variant, $deviceId, $limit);

        return [$variant, $list];
    }

    protected function resolveVariant(): string
    {
        $existing = request()->cookie(self::COOKIE_NAME);
        if (in_array($existing, ['A', 'B'], true)) {
            return $existing;
        }

        $variant = $this->pickVariant();

        Cookie::queue(self::COOKIE_NAME, $variant, self::COOKIE_LIFETIME_MINUTES);

        return $variant;
    }

    protected function pickVariant(): string
    {
        $weights = config('recs.ab_split', ['A' => 50.0, 'B' => 50.0]);
        $weightA = (float) ($weights['A'] ?? 50.0);
        $weightB = (float) ($weights['B'] ?? 50.0);
        $total = $weightA + $weightB;

        if ($total <= 0.0) {
            return 'A';
        }

        $threshold = $weightA / $total;
        $random = random_int(0, 10000) / 10000;

        return $random < $threshold ? 'A' : 'B';
    }

    /**
     * @return Collection<int,Movie>
     */
    protected function score(string $variant, string $deviceId, int $limit): Collection
    {
        $weights = $this->weights->forVariant($variant);
        $profile = $this->preferences->profileForDevice($deviceId);
        $now = CarbonImmutable::now();

        $movies = Movie::query()
            ->orderByDesc('imdb_votes')
            ->limit(250)
            ->get();

        $scored = $movies->map(function (Movie $movie) use ($weights, $profile, $now) {
            $pop = $this->popularityScore($movie);
            $recent = $this->recencyScore($movie, $now);
            $pref = $profile->score($movie);

            $score = ($weights['pop'] * $pop)
                + ($weights['recent'] * $recent)
                + ($weights['pref'] * $pref);

            $movie->setAttribute('rec_variant_scores', [
                'pop' => round($pop, 4),
                'recent' => round($recent, 4),
                'pref' => round($pref, 4),
                'total' => round($score, 4),
            ]);
            $movie->setAttribute('rec_variant_weights', $weights);

            return ['movie' => $movie, 'score' => $score];
        })->sortByDesc('score')->take($limit);

        return $scored->pluck('movie')->values();
    }

    private function popularityScore(Movie $movie): float
    {
        $rating = (float) ($movie->imdb_rating ?? 0.0);
        $votes = max(0, (int) ($movie->imdb_votes ?? 0));

        if ($votes <= 0) {
            return max(0.0, min(1.0, $rating / 10));
        }

        $voteFactor = max(0.0, log10($votes + 1));
        $voteNormalized = min(1.0, $voteFactor / 6.0);
        $ratingNormalized = max(0.0, min(1.0, $rating / 10.0));

        return round($ratingNormalized * 0.6 + $voteNormalized * 0.4, 4);
    }

    private function recencyScore(Movie $movie, CarbonImmutable $now): float
    {
        $release = $movie->release_date;
        if ($release instanceof CarbonImmutable) {
            $ageMonths = max(0, $release->diffInMonths($now));
            if ($ageMonths === 0) {
                return 1.0;
            }

            return round(max(0.0, 1.0 - min(1.0, $ageMonths / 60.0)), 4);
        }

        $year = $movie->year;
        if ($year === null) {
            return 0.0;
        }

        $delta = abs($now->year - (int) $year);
        $score = max(0.0, 1.0 - min(1.0, $delta / 10.0));

        return round($score, 4);
    }
}
