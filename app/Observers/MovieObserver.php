<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Movie;
use App\Support\AnalyticsCache;

class MovieObserver
{
    public function __construct(private readonly AnalyticsCache $cache) {}

    public function saved(Movie $movie): void
    {
        $this->flushTrends();
    }

    public function deleted(Movie $movie): void
    {
        $this->flushTrends();
    }

    private function flushTrends(): void
    {
        $this->cache->flushTrends();
        $this->cache->flushTrending();
    }
}
