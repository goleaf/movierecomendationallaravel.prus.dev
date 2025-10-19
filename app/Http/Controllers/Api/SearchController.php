<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\SearchResultCollection;
use App\Models\Movie;
use App\Support\MovieSearchFilters;
use Illuminate\Database\Eloquent\Builder;

class SearchController extends Controller
{
    public function index(SearchRequest $request): SearchResultCollection
    {
        $filters = MovieSearchFilters::fromRequest($request);

        /** @var Builder<Movie> $query */
        $query = $filters->apply(Movie::query());

        $items = $query
            ->orderByDesc('imdb_votes')
            ->orderByDesc('imdb_rating')
            ->limit($request->perPage())
            ->get();

        return new SearchResultCollection($items);
    }
}
