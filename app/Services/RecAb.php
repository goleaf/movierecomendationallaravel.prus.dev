<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

class RecAb
{
    private const COOKIE_NAME = 'ab_variant';

    private const COOKIE_LIFETIME_MINUTES = 60 * 24 * 365 * 5;

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
        $weights = config('recs.ab_split', ['A' => 50.0, 'B' => 50.0]);
        $weightA = (float) ($weights['A'] ?? 50.0);
        $weightB = (float) ($weights['B'] ?? 50.0);
        $total = $weightA + $weightB;

        if ($total <= 0.0) {
            return 'A';
        }

        $threshold = $weightA / $total;
        $seed = config('recs.seed');
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
        $W = config("recs.$variant", ['pop' => 0.5, 'recent' => 0.2, 'pref' => 0.3]);
        $movies = Movie::query()->orderByDesc('imdb_votes')->limit(200)->get();

        $currentYear = now()->year;

        return $movies->map(function (Movie $movie) use ($W, $currentYear) {
            $weightedScore = $movie->weighted_score;
            $popularity = $weightedScore / 10.0;
            $recency = $movie->year
                ? max(0.0, (5 - ($currentYear - (int) $movie->year))) / 5.0
                : 0.0;
            $score = ($W['pop'] * $popularity) + ($W['recent'] * $recency) + ($W['pref'] * 0.0);

            return ['m' => $movie, 's' => $score];
        })->sortByDesc('s')->pluck('m')->take($limit);
    }
}
