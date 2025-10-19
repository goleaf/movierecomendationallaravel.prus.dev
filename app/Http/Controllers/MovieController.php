<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Services\RecommendationLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function show(Movie $movie, Request $request, RecommendationLogger $logger): View
    {
        $placement = (string) $request->query('placement', 'unknown');
        $variant = (string) $request->query('variant', 'unknown');
        $logger->recordClick(device_id(), $variant, $placement, (int) $movie->id);

        return view('movies.show', compact('movie'));
    }
}
