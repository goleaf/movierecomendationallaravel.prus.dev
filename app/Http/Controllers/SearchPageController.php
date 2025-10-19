<?php

namespace App\Http\Controllers;

use App\Http\Resources\SearchResultCollection;
use App\Models\Movie;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchPageController extends Controller
{
    public function __invoke(Request $request): View|SearchResultCollection|JsonResponse
    {
        $queryString = trim((string) $request->query('q', ''));
        $type = $request->query('type');
        $genre = $request->query('genre');
        $yearFrom = (int) $request->query('yf', 0);
        $yearTo = (int) $request->query('yt', 0);

        /** @var Builder<Movie> $query */
        $query = Movie::query();

        if ($queryString !== '') {
            $query->where(function (Builder $builder) use ($queryString): void {
                $builder
                    ->where('title', 'like', '%' . $queryString . '%')
                    ->orWhere('imdb_tt', $queryString);
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($genre) {
            $query->whereJsonContains('genres', $genre);
        }

        if ($yearFrom) {
            $query->where('year', '>=', $yearFrom);
        }

        if ($yearTo) {
            $query->where('year', '<=', $yearTo);
        }

        $items = $query
            ->orderByDesc('imdb_votes')
            ->orderByDesc('imdb_rating')
            ->limit(40)
            ->get();

        if ($request->wantsJson()) {
            return new SearchResultCollection($items);
        }

        return view('search.index', [
            'q' => $queryString,
            'items' => $items,
            'type' => $type,
            'genre' => $genre,
            'yf' => $yearFrom,
            'yt' => $yearTo,
        ]);
    }
}
