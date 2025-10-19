<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Collection;

/**
 * @phpstan-type RecommendationPayload array{variant: string, movies: Collection<int, Movie>}
 */
final class Recommendation
{
    /**
     * @param  Collection<int, Movie>  $movies
     */
    public function __construct(
        private readonly string $variant,
        private readonly Collection $movies,
    ) {
    }

    public function variant(): string
    {
        return $this->variant;
    }

    /**
     * @return Collection<int, Movie>
     */
    public function movies(): Collection
    {
        return $this->movies;
    }

    public function isEmpty(): bool
    {
        return $this->movies->isEmpty();
    }

    /**
     * @return RecommendationPayload
     */
    public function toArray(): array
    {
        return [
            'variant' => $this->variant(),
            'movies' => $this->movies(),
        ];
    }
}
