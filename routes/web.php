<?php

use App\Http\Controllers\CtrSvgBarsController;
use App\Http\Controllers\CtrSvgController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\LandingPageRenderer;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\SearchPageController;
use App\Http\Controllers\SsrIssuesController;
use App\Livewire\HomePage;
use App\Livewire\TrendsPage;
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
    Route::permanentRedirect('/ctr', '/analytics/ctr')->name('ctr');
    Route::get('/ctr.svg', [CtrSvgController::class, 'line'])->name('ctr.svg');
    Route::get('/ctr/bars.svg', [CtrSvgBarsController::class, 'bars'])->name('ctr.bars.svg');
    Route::permanentRedirect('/metrics', '/analytics/queue')->name('metrics');
    Route::get('/ssr/issues', SsrIssuesController::class)->name('ssr.issues');
});

Route::prefix('flirt')->group(function (): void {
    Route::get('/', LandingPageRenderer::class)->name('landing-page');

    Route::controller(InquiryController::class)
        ->prefix('contact-us')
        ->as('contact.')
        ->group(function (): void {
            Route::get('/', 'create')->name('form');
            Route::post('/', 'store')->name('submit');
        });
});
