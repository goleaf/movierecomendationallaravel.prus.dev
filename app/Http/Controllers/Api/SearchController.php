<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SearchResultCollection;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): SearchResultCollection
    {
        $queryString = trim((string) $request->query('q', ''));
        $type = $request->query('type');
        $genre = $request->query('genre');
        $yearFrom = (int) $request->query('yf', 0);
        $yearTo = (int) $request->query('yt', 0);
        $perPage = (int) $request->query('per', 20);
        $perPage = min(50, max(1, $perPage));

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
            ->limit($perPage)
            ->get();

        return new SearchResultCollection($items);
    }
}
