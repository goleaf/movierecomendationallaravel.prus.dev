<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Collection;

class RecAb
{
    /** @return array{0:string,1:Collection<int,Movie>} */
    public function forDevice(string $deviceId, int $limit = 12): array
    {
        $variant = (crc32($deviceId) % 2 === 0) ? 'A' : 'B';
        $list = $this->score($variant, $deviceId, $limit);

        return [$variant, $list];
    }

    /** @return Collection<int,Movie> */
    protected function score(string $variant, string $deviceId, int $limit): Collection
    {
        $W = config("recs.$variant", ['pop' => 0.5, 'recent' => 0.2, 'pref' => 0.3]);
        $movies = Movie::query()->orderByDesc('imdb_votes')->limit(200)->get();

        return $movies->map(function (Movie $m) use ($W) {
            $pop = ((float) ($m->imdb_rating ?? 0)) / 10 * (max(0.0, (float) log10(($m->imdb_votes ?? 0) + 1)) / 6);
            $recent = $m->year ? max(0.0, (5 - (now()->year - (int) $m->year))) / 5.0 : 0.0;
            $score = $W['pop'] * $pop + $W['recent'] * $recent + $W['pref'] * 0.0;

            return ['m' => $m, 's' => $score];
        })->sortByDesc('s')->pluck('m')->take($limit);
    }
}
