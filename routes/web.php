<?php

use App\Http\Controllers\CtrSvgBarsController;
use App\Http\Controllers\CtrSvgController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\SearchPageController;
use App\Http\Controllers\SsrIssuesController;
use App\Http\Controllers\TrendsController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', HomePage::class)->name('home');
Route::get('/search', SearchPageController::class)->name('search');
Route::get('/trends', TrendsPage::class)->name('trends');
Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('movies.show');

Route::get('/works', function () {
    $path = base_path('WORKS.md');
    abort_unless(File::exists($path), 404);

    $markdown = File::get($path);
    $content = Str::markdown($markdown);

    return view('works', ['content' => $content]);
})->name('works');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/ctr.svg', [CtrSvgController::class, 'line'])->name('ctr.svg');
    Route::get('/ctr/bars.svg', [CtrSvgBarsController::class, 'bars'])->name('ctr.bars.svg');
    Route::get('/ssr/issues', SsrIssuesController::class)->name('ssr.issues');
});
