<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rss;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class UpcomingFeedController extends AbstractMovieFeedController
{
    protected function baseQuery(): Builder
    {
        $today = CarbonImmutable::now('UTC')->toDateString();

        return Movie::query()
            ->whereNotNull('release_date')
            ->where('release_date', '>', $today);
    }

    protected function applyOrdering(Builder $query): Builder
    {
        return $query
            ->orderBy('release_date')
            ->orderBy('id');
    }

    protected function feedTitle(): string
    {
        return 'Скоро выйдут';
    }

    protected function feedDescription(): string
    {
        return 'Предстоящие премьеры и анонсы, которые скоро появятся на экранах.';
    }
}
