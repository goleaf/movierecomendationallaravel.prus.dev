<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Settings\RecommendationWeightsSettings;
use App\Support\ArrayHelpers;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;

class RecAb
{
    private const COOKIE_NAME = 'ab_variant';

    private const COOKIE_LIFETIME_MINUTES = 60 * 24 * 365 * 5;

    private ?RecommendationWeightsSettings $settings = null;

    public function __construct(private readonly SettingsRepository $settingsRepository)
    {
    }

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
        $weights = $this->settings()->ab_split;
        $weightA = (float) ($weights['A'] ?? 50.0);
        $weightB = (float) ($weights['B'] ?? 50.0);
        $total = $weightA + $weightB;

        if ($total <= 0.0) {
            return 'A';
        }

        $threshold = $weightA / $total;
        $seed = $this->settings()->seed;
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
        /** @var array<string, float|int> $rawWeights */
        $rawWeights = $this->settings()->{$variant} ?? [];
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

        $aggregates = [];

        if (Schema::hasTable('rec_clicks')) {
            $rows = DB::table('rec_clicks')
                ->selectRaw('movie_id, count(*) as total')
                ->where('device_id', $deviceId)
                ->whereNotNull('movie_id')
                ->groupBy('movie_id')
                ->get()
                ->map(static fn (object $row): array => [
                    'movie_id' => $row->movie_id === null ? null : (int) $row->movie_id,
                    'total' => (float) $row->total,
                ])
                ->all();

            $aggregates = $this->mergeMovieAggregates($aggregates, $rows);
        }

        if (Schema::hasTable('device_history')) {
            $rows = DB::table('device_history')
                ->selectRaw('movie_id, count(*) as total')
                ->where('device_id', $deviceId)
                ->whereNotNull('movie_id')
                ->groupBy('movie_id')
                ->get()
                ->map(static fn (object $row): array => [
                    'movie_id' => $row->movie_id === null ? null : (int) $row->movie_id,
                    'total' => (float) $row->total,
                ])
                ->all();

            $aggregates = $this->mergeMovieAggregates($aggregates, $rows);
        }

        if (! ArrayHelpers::recursiveContains($aggregates, static function (mixed $value): bool {
            return is_array($value) && array_key_exists('movie_id', $value);
        })) {
            return [];
        }

        if (ArrayHelpers::recursiveFindByKeyValue(
            $aggregates,
            'total',
            static fn (mixed $value): bool => is_numeric($value) && (float) $value > 0.0,
        ) === null) {
            return [];
        }

        $sum = array_reduce($aggregates, static function (float $carry, array $entry): float {
            return $carry + (float) $entry['total'];
        }, 0.0);

        if ($sum <= 0.0) {
            return [];
        }

        $scores = [];

        foreach ($aggregates as $entry) {
            $movieId = $entry['movie_id'];

            if (! is_int($movieId)) {
                continue;
            }

            $scores[$movieId] = $entry['total'] / $sum;
        }

        return $scores;
    }

    /**
     * @param  array<int, array{movie_id:int, total:float}>  $existing
     * @param  array<int, array{movie_id:int|null, total:float}>  $rows
     * @return array<int, array{movie_id:int, total:float}>
     */
    private function mergeMovieAggregates(array $existing, array $rows): array
    {
        foreach ($rows as $row) {
            $movieId = $row['movie_id'];

            if ($movieId === null) {
                continue;
            }

            $index = ArrayHelpers::columnSearch($existing, 'movie_id', $movieId);

            if ($index === null) {
                $existing[] = [
                    'movie_id' => (int) $movieId,
                    'total' => (float) $row['total'],
                ];

                continue;
            }

            $existing[$index]['total'] += (float) $row['total'];
        }

        return $existing;
    }

    private function settings(): RecommendationWeightsSettings
    {
        if ($this->settings === null) {
            $this->settings = RecommendationWeightsSettings::fromRepository($this->settingsRepository);
        }

        return $this->settings;
    }
}
