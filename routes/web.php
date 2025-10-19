<?php

use App\Http\Controllers\CtrSvgBarsController;
use App\Http\Controllers\CtrSvgController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\SearchPageController;
use App\Http\Controllers\SsrIssuesController;
use App\Livewire\HomePage;
use App\Livewire\TrendsPage;
use Illuminate\Support\Facades\Route;

Route::get('/', HomePage::class)->name('home');
Route::get('/search', SearchPageController::class)->name('search');
Route::get('/trends', TrendsPage::class)->name('trends');
Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('movies.show');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/ctr.svg', [CtrSvgController::class, 'line'])->name('ctr.svg');
    Route::get('/ctr/bars.svg', [CtrSvgBarsController::class, 'bars'])->name('ctr.bars.svg');
    Route::get('/ssr/issues', SsrIssuesController::class)->name('ssr.issues');
});
