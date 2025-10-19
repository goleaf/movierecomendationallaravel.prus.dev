<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SearchResultCollection;
use App\Models\Movie;
use App\Support\MovieSearchFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): SearchResultCollection
    {
        $filters = MovieSearchFilters::fromRequest($request);

        /** @var Builder<Movie> $query */
        $query = $filters->apply(Movie::query());

        $items = $query
            ->orderByDesc('imdb_votes')
            ->orderByDesc('imdb_rating')
            ->limit($this->resolveLimit($request))
            ->get();

        return new SearchResultCollection($items);
    }

    private function resolveLimit(Request $request): int
    {
        $perPage = (int) $request->query('per', 20);

        return min(50, max(1, $perPage));
    }
}
