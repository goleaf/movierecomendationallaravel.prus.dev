<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SearchResultCollection;
use App\Models\Movie;
use App\Support\MovieSearchFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchPageController extends Controller
{
    public function __invoke(Request $request): View|SearchResultCollection|JsonResponse
    {
        $filters = MovieSearchFilters::fromRequest($request);

        $items = $filters
            ->apply(Movie::query())
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
