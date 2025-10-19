<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MovieClickRequest;
use App\Http\Requests\MovieIndexRequest;
use App\Models\Movie;
use App\Models\User;
use App\Services\RecommendationLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class MovieController extends Controller
{
    public function index(MovieIndexRequest $request): JsonResponse|View
    {
        $filters = $request->filters();

        $paginator = $filters->apply(Movie::query())
            ->paginate(24, ['*'], 'page', $filters->page)
            ->withQueryString();

        if ($request->wantsJson()) {
            return new JsonResponse([
                'filters' => $filters->toArray(),
                'meta' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
                'data' => $paginator->items(),
            ]);
        }

        return view('movies.index', [
            'movies' => $paginator,
            'filters' => $filters,
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
}
