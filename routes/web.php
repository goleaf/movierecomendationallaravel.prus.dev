<?php

use App\Http\Controllers\AdminMetricsController;
use App\Http\Controllers\CtrController;
use App\Http\Controllers\CtrSvgBarsController;
use App\Http\Controllers\CtrSvgController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\SearchPageController;
use App\Http\Controllers\SsrIssuesController;
use App\Http\Controllers\TrendsController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', SearchPageController::class)->name('search');
Route::get('/trends', TrendsController::class)->name('trends');
Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('movies.show');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/ctr', [CtrController::class, 'index'])->name('ctr');
    Route::get('/ctr.svg', [CtrSvgController::class, 'line'])->name('ctr.svg');
    Route::get('/ctr/bars.svg', [CtrSvgBarsController::class, 'bars'])->name('ctr.bars.svg');
    Route::get('/metrics', [AdminMetricsController::class, 'index'])->name('metrics');
    Route::get('/ssr/issues', SsrIssuesController::class)->name('ssr.issues');
});
