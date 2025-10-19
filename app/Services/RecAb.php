<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Collection;

class RecAb
{
    /**
     * @return array{0:string,1:Collection<int,Movie>}
     */
    public function forDevice(string $deviceId, int $limit = 12): array
    {
        $variant = (crc32($deviceId) % 2 === 0) ? 'A' : 'B';

        $list = $this->score($variant, $limit);

        return [$variant, $list];
    }

    /**
     * @return Collection<int,Movie>
     */
    protected function score(string $variant, int $limit): Collection
    {
        $weights = $this->weightsForVariant($variant);

        $movies = Movie::query()
            ->orderByDesc('imdb_votes')
            ->limit(200)
            ->get();

        return $movies
            ->map(function (Movie $movie) use ($weights): array {
                $popularity = ((float) ($movie->imdb_rating ?? 0)) / 10 * (
                    max(0.0, (float) log10(($movie->imdb_votes ?? 0) + 1)) / 6
                );
                $recency = $movie->year
                    ? max(0.0, (5 - (now()->year - (int) $movie->year))) / 5.0
                    : 0.0;
                $score = $weights['pop'] * $popularity
                    + $weights['recent'] * $recency
                    + $weights['pref'] * 0.0;

                return ['movie' => $movie, 'score' => $score];
            })
            ->sortByDesc('score')
            ->pluck('movie')
            ->take($limit);
    }

    /**
     * @return array{pop:float,recent:float,pref:float}
     */
    protected function weightsForVariant(string $variant): array
    {
        $defaults = [
            'pop' => 0.5,
            'recent' => 0.2,
            'pref' => 0.3,
        ];

        $configured = config("recs.$variant");
        if (is_array($configured)) {
            $weights = array_merge($defaults, $configured);
        } else {
            $weights = $defaults;
        }

        return [
            'pop' => (float) $weights['pop'],
            'recent' => (float) $weights['recent'],
            'pref' => (float) $weights['pref'],
        ];
    }
}
