<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rss;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class NewReleasesFeedController extends AbstractMovieFeedController
{
    protected function baseQuery(): Builder
    {
        $today = CarbonImmutable::now('UTC')->toDateString();

        return Movie::query()
            ->whereNotNull('release_date')
            ->where('release_date', '<=', $today);
    }

    protected function applyOrdering(Builder $query): Builder
    {
        return $query
            ->orderByDesc('release_date')
            ->orderByDesc('id');
    }

    protected function feedTitle(): string
    {
        return 'Новые релизы';
    }

    protected function feedDescription(): string
    {
        return 'Свежие фильмы и сериалы, недавно вышедшие в прокат.';
    }
}
