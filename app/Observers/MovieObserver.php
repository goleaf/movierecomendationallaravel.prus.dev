<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Movie;
use App\Support\AnalyticsCache;

class MovieObserver
{
    public function created(Movie $movie): void
    {
        AnalyticsCache::flushTrends();
    }

    public function updated(Movie $movie): void
    {
        AnalyticsCache::flushTrends();
    }

    public function deleted(Movie $movie): void
    {
        AnalyticsCache::flushTrends();
    }
}
