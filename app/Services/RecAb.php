<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Settings\RecommendationWeightsSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecAb
{
    private const COOKIE_NAME = 'ab_variant';

    private const COOKIE_LIFETIME_MINUTES = 60 * 24 * 365 * 5;

    public function __construct(private RecommendationWeightsSettings $settings) {}

    /** @return array{0:string,1:Collection<int,Movie>} */
    public function forDevice(string $deviceId, int $limit = 12): array
    {
        $variant = $this->resolveVariant($deviceId);
        $list = $this->score($variant, $deviceId, $limit);

        return [$variant, $list];
    }

    protected function resolveVariant(string $deviceId): string
    {
        $request = request();
        $existing = $request instanceof Request ? $request->cookie(self::COOKIE_NAME) : null;
        if (in_array($existing, ['A', 'B'], true)) {
            if ($request instanceof Request) {
                $request->attributes->set('ab_variant', $existing);
            }

            return $existing;
        }

        $variant = $this->pickVariant($deviceId);

        Cookie::queue(self::COOKIE_NAME, $variant, self::COOKIE_LIFETIME_MINUTES);

        if ($request instanceof Request) {
            $request->attributes->set('ab_variant', $variant);
        }

        return $variant;
    }

    protected function pickVariant(string $deviceId): string
    {
        $weights = $this->settings->ab_split;
        $weightA = (float) ($weights['A'] ?? 50.0);
        $weightB = (float) ($weights['B'] ?? 50.0);
        $total = $weightA + $weightB;

        if ($total <= 0.0) {
            return 'A';
        }

        $threshold = $weightA / $total;
        $seed = $this->settings->seed;
        $random = is_string($seed) && $seed !== ''
            ? $this->deterministicRandom($deviceId, $seed)
            : random_int(0, 10000) / 10000;

        return $random < $threshold ? 'A' : 'B';
    }

    private function deterministicRandom(string $deviceId, string $seed): float
    {
        $hash = crc32($seed.'|'.$deviceId);
        $unsigned = (int) sprintf('%u', $hash);

        return $unsigned / 4294967295;
    }

    /** @return Collection<int,Movie> */
    protected function score(string $variant, string $deviceId, int $limit): Collection
    {
        $rawWeights = $this->settings->weightsForVariant($variant);
        $weights = $this->normaliseWeights($rawWeights);

        $movies = Movie::query()->orderByDesc('imdb_votes')->limit(200)->get();

        $preferenceScores = $weights['pref'] > 0.0
            ? $this->preferenceScoresForDevice($deviceId)
            : [];

        $currentYear = now()->year;

        return $movies->map(function (Movie $movie) use ($weights, $currentYear, $preferenceScores) {
            $weightedScore = $movie->weighted_score;
            $popularity = $weightedScore / 10.0;
            $recency = $movie->year
                ? max(0.0, (5 - ($currentYear - (int) $movie->year))) / 5.0
                : 0.0;
            $preference = $preferenceScores[$movie->id] ?? 0.0;

            $score = ($weights['pop'] * $popularity) + ($weights['recent'] * $recency);

            if ($weights['pref'] > 0.0) {
                $score += $weights['pref'] * $preference;
            }

            return ['m' => $movie, 's' => $score];
        })->sortByDesc('s')->pluck('m')->take($limit);
    }

    /**
     * @param  array<string, float|int>  $weights
     * @return array{pop: float, recent: float, pref: float}
     */
    private function normaliseWeights(array $weights): array
    {
        $defaults = ['pop' => 0.5, 'recent' => 0.2, 'pref' => 0.3];

        $clamped = [];

        foreach ($defaults as $key => $default) {
            $value = array_key_exists($key, $weights) ? (float) $weights[$key] : $default;
            $clamped[$key] = max(0.0, $value);
        }

        $total = array_sum($clamped);

        if ($total <= 0.0) {
            return $defaults;
        }

        return array_map(static fn (float $value): float => $value / $total, $clamped);
    }

    /**
     * @return array<int, float>
     */
    protected function preferenceScoresForDevice(string $deviceId): array
    {
        if ($deviceId === '') {
            return [];
        }

        $scores = [];

        if (Schema::hasTable('rec_clicks')) {
            $clicks = DB::table('rec_clicks')
                ->selectRaw('movie_id, count(*) as total')
                ->where('device_id', $deviceId)
                ->whereNotNull('movie_id')
                ->groupBy('movie_id')
                ->pluck('total', 'movie_id')
                ->all();

            foreach ($clicks as $movieId => $total) {
                if ($movieId === null) {
                    continue;
                }

                $scores[(int) $movieId] = ($scores[(int) $movieId] ?? 0.0) + (float) $total;
            }
        }

        if (Schema::hasTable('device_history')) {
            $history = DB::table('device_history')
                ->selectRaw('movie_id, count(*) as total')
                ->where('device_id', $deviceId)
                ->whereNotNull('movie_id')
                ->groupBy('movie_id')
                ->pluck('total', 'movie_id')
                ->all();

            foreach ($history as $movieId => $total) {
                if ($movieId === null) {
                    continue;
                }

                $scores[(int) $movieId] = ($scores[(int) $movieId] ?? 0.0) + (float) $total;
            }
        }

        if ($scores === []) {
            return [];
        }

        $sum = array_sum($scores);

        if ($sum <= 0.0) {
            return [];
        }

        return array_map(static fn (float $value): float => $value / $sum, $scores);
    }
}
