<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;

final class DevicePreferenceProfile
{
    /**
     * @param  array<string, float>  $genreWeights
     * @param  array<string, float>  $typeWeights
     */
    public function __construct(
        private readonly array $genreWeights,
        private readonly float $maxGenreWeight,
        private readonly array $typeWeights,
        private readonly float $typeWeightSum,
        private readonly ?float $preferredYear,
        private readonly ?float $yearSpread,
        private readonly int $samples,
    ) {
    }

    public static function empty(): self
    {
        return new self([], 0.0, [], 0.0, null, null, 0);
    }

    public function hasSignals(): bool
    {
        return $this->samples > 0 && ($this->maxGenreWeight > 0.0 || $this->typeWeightSum > 0.0 || $this->preferredYear !== null);
    }

    public function interactions(): int
    {
        return $this->samples;
    }

    public function score(Movie $movie): float
    {
        if ($this->samples <= 0) {
            return 0.0;
        }

        $genreScore = $this->scoreGenres($movie);
        $typeScore = $this->scoreType($movie);
        $yearScore = $this->scoreYear($movie);

        $combined = (0.6 * $genreScore) + (0.15 * $typeScore) + (0.25 * $yearScore);

        if (! is_finite($combined) || $combined < 0.0) {
            return 0.0;
        }

        return min(1.0, $combined);
    }

    private function scoreGenres(Movie $movie): float
    {
        if ($this->maxGenreWeight <= 0.0) {
            return 0.0;
        }

        $genres = $movie->genres;
        if (! is_array($genres) || $genres === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($genres as $genre) {
            if (! is_string($genre)) {
                continue;
            }

            $key = mb_strtolower($genre);
            $total += $this->genreWeights[$key] ?? 0.0;
        }

        if ($total <= 0.0) {
            return 0.0;
        }

        $denominator = $this->maxGenreWeight * max(count($genres), 1);
        if ($denominator <= 0.0) {
            return 0.0;
        }

        $score = $total / $denominator;

        return min(1.0, max(0.0, $score));
    }

    private function scoreType(Movie $movie): float
    {
        if ($this->typeWeightSum <= 0.0) {
            return 0.0;
        }

        $type = $movie->type;
        if (! is_string($type) || $type === '') {
            return 0.0;
        }

        $key = mb_strtolower($type);
        $weight = $this->typeWeights[$key] ?? 0.0;

        if ($weight <= 0.0) {
            return 0.0;
        }

        $score = $weight / $this->typeWeightSum;

        return min(1.0, max(0.0, $score));
    }

    private function scoreYear(Movie $movie): float
    {
        if ($this->preferredYear === null) {
            return 0.0;
        }

        $year = $movie->year;
        if ($year === null) {
            return 0.0;
        }

        $spread = $this->yearSpread ?? 6.0;
        $spread = max(3.0, $spread);
        $delta = abs((float) $year - $this->preferredYear);
        if (! is_finite($delta)) {
            return 0.0;
        }

        $score = 1.0 - ($delta / (2.0 * $spread));

        return min(1.0, max(0.0, $score));
    }
}
