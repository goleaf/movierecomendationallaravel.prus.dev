<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MovieClickRequest;
use App\Models\Movie;
use App\Models\User;
use App\Services\RecommendationLogger;
use App\Support\PaginatorQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    private const SORT_OPTIONS = [
        'score' => 'Top rated first',
        'recent' => 'Recently released',
        'oldest' => 'Oldest releases',
    ];

    public function index(Request $request): View
    {
        $queryParams = PaginatorQuery::fromRequest($request);
        $queryParams->applyTo($request);

        $sort = $this->resolveSort($queryParams->get('sort'));
        $genres = $queryParams->values('genres');
        $yearFrom = $queryParams->get('year_from');
        $yearTo = $queryParams->get('year_to');

        $moviesQuery = Movie::query();

        if (($search = $queryParams->get('q')) !== null) {
            $moviesQuery->where(static function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('imdb_tt', $search);
            });
        }

        if (($type = $queryParams->get('type')) !== null) {
            $moviesQuery->where('type', $type);
        }

        foreach ($genres as $genre) {
            $moviesQuery->whereJsonContains('genres', $genre);
        }

        if ($yearFrom !== null) {
            $moviesQuery->where('year', '>=', (int) $yearFrom);
        }

        if ($yearTo !== null) {
            $moviesQuery->where('year', '<=', (int) $yearTo);
        }

        $movies = $this->applySort($moviesQuery, $sort)
            ->paginate(12)
            ->withQueryString();

        $recommendations = Movie::query()
            ->when($genres !== [], static fn (Builder $builder) => $builder->whereJsonContains('genres', $genres[0]))
            ->orderByDesc('weighted_score')
            ->paginate(6, ['*'], 'recommendations_page')
            ->appends($queryParams->only('genres', 'sort', 'year_from', 'year_to'));

        return view('movies.index', [
            'movies' => $movies,
            'recommendations' => $recommendations,
            'query' => $queryParams,
            'currentSort' => $sort,
            'sortOptions' => self::SORT_OPTIONS,
        ]);
    }

    public function show(Movie $movie, MovieClickRequest $request, RecommendationLogger $logger): View
    {
        $logger->recordClick(
            device_id(),
            $request->variant(),
            $request->placement(),
            (int) $movie->id,
        );

        $movie->loadCount('comments');

        return view('movies.show', [
            'movie' => $movie,
            'commentMentionables' => User::mentionableForComments(),
        ]);
    }

    private function resolveSort(mixed $value): string
    {
        if (! is_string($value)) {
            return 'score';
        }

        $normalized = trim($value);

        return array_key_exists($normalized, self::SORT_OPTIONS) ? $normalized : 'score';
    }

    private function applySort(Builder $builder, string $sort): Builder
    {
        return match ($sort) {
            'recent' => $builder->orderByDesc('release_date')->orderByDesc('year'),
            'oldest' => $builder->orderBy('release_date')->orderBy('year'),
            default => $builder->orderByDesc('weighted_score')->orderByDesc('imdb_votes'),
        };
    }
}
