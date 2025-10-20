<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MovieClickRequest;
use App\Models\Movie;
use App\Models\User;
use App\Services\RecommendationLogger;
use Illuminate\Contracts\View\View;

class MovieController extends Controller
{
    public function show(Movie $movie, MovieClickRequest $request, RecommendationLogger $logger): View
    {
        $logger->recordClick(
            device_id(),
            $request->variant(),
            $request->placement(),
            (int) $movie->id,
        );

        $movie->loadCount('comments');
        Movie::ensureListRelationsLoaded($movie);

        return view('movies.show', [
            'movie' => $movie,
            'commentMentionables' => User::mentionableForComments(),
        ]);
    }
}
