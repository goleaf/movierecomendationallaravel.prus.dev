<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Collection;

class RecAb
{
    /**
     * @return array{0: string, 1: Collection<int, Movie>}
     */
    public function forDevice(string $deviceId, int $limit = 12): array
    {
        $variant = (crc32($deviceId) % 2 === 0) ? 'A' : 'B';
        $list = $this->score($variant, $deviceId, $limit);

        return [$variant, $list];
    }

    /**
     * @return Collection<int, Movie>
     */
    protected function score(string $variant, string $deviceId, int $limit): Collection
    {
        /** @var array{pop: float, recent: float, pref: float} $weights */
        $weights = config("recs.$variant", ['pop' => 0.5, 'recent' => 0.2, 'pref' => 0.3]);

        /** @var Collection<int, Movie> $movies */
        $movies = Movie::query()
            ->orderByDesc('imdb_votes')
            ->limit(200)
            ->get();

        return $movies
            ->map(static function (Movie $movie) use ($weights): array {
                $popularity = ((float) ($movie->imdb_rating ?? 0)) / 10.0;
                $voteFactor = (float) log10(($movie->imdb_votes ?? 0) + 1);
                $popularity *= max(0.0, $voteFactor) / 6.0;

                $recent = $movie->year !== null
                    ? max(0.0, (5 - (now()->year - (int) $movie->year))) / 5.0
                    : 0.0;

                $score = ($weights['pop'] * $popularity)
                    + ($weights['recent'] * $recent)
                    + ($weights['pref'] * 0.0);

                return [
                    'movie' => $movie,
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->map(static fn (array $entry): Movie => $entry['movie']);
    }
}
