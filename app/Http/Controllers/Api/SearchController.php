<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiSearchRequest;
use App\Http\Resources\SearchResultCollection;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Builder;

class SearchController extends Controller
{
    public function index(ApiSearchRequest $request): SearchResultCollection
    {
        $filters = $request->filters();

        /** @var Builder<Movie> $query */
        $query = $filters->apply(Movie::query());

        $items = $query
            ->orderByDesc('imdb_votes')
            ->orderByDesc('imdb_rating')
            ->limit($request->limit())
            ->get();

        return new SearchResultCollection($items);
    }
}
