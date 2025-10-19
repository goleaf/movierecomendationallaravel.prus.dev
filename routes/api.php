<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\TrendsController;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use Illuminate\Support\Facades\Route;

Route::get('/search', [SearchController::class, 'index'])->name('api.search');

Route::get('/movies/{movie}', function (Movie $movie) {
    return new MovieResource($movie);
})->name('api.movies.show');

Route::get('/trends', TrendsController::class)->name('api.trends');
