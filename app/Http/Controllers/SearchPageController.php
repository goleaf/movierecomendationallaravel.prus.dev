<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchFiltersRequest;
use App\Http\Resources\SearchResultCollection;
use App\Models\Movie;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SearchPageController extends Controller
{
    public function __invoke(SearchFiltersRequest $request): View|SearchResultCollection|JsonResponse
    {
        $filters = $request->filters();

        /** @var Builder<Movie> $query */
        $query = $filters->apply(Movie::query());

        $items = $query
            ->orderByDesc('imdb_votes')
            ->orderByDesc('imdb_rating')
            ->limit(40)
            ->get();

        if ($request->wantsJson()) {
            return new SearchResultCollection($items);
        }

        return view('search.index', array_merge(
            ['items' => $items],
            $filters->toViewData(),
        ));
    }
}
