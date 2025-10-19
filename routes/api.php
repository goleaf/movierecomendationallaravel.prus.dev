<?php

declare(strict_types=1);

use App\Exceptions\Handler as ExceptionHandler;
use App\Http\Controllers\Api\ArtworkProxyController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('noindex')->group(function (): void {
    Route::get('/search', [SearchController::class, 'index'])->name('api.search');

    Route::get('/movies/{movie}', function (Movie $movie) {
        return new MovieResource($movie);
    })->name('api.movies.show');

    Route::get('/artwork', ArtworkProxyController::class)->name('api.artwork');
});

Route::fallback(function (Request $request) {
    if ($request->expectsJson() || $request->is('api/*')) {
        return response()->json(ExceptionHandler::formatErrorResponse($request, 404), 404);
    }

    abort(404);
})->name('api.fallback');
