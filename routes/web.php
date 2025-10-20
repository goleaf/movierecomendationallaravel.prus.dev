<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\InquiryController as AdminInquiryController;
use App\Http\Controllers\AdminSsrController;
use App\Http\Controllers\CtrSvgBarsController;
use App\Http\Controllers\CtrSvgController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\LandingPageRenderer;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\PosterProxyController;
use App\Http\Controllers\Rss\NewReleasesFeedController;
use App\Http\Controllers\Rss\UpcomingFeedController;
use App\Http\Controllers\SearchPageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SsrIssuesController;
use App\Http\Middleware\AutoTranslate;
use App\Http\Middleware\ResolveHost;
use App\Livewire\HomePage;
use App\Livewire\TrendsPage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::middleware([ResolveHost::class])->group(function (): void {
    Route::get('/', HomePage::class)->name('home');
    Route::get('/search', SearchPageController::class)->name('search');
    Route::get('/trends', TrendsPage::class)->name('trends');
    Route::get('/movies/{movie}', [MovieController::class, 'show'])
        ->middleware(AutoTranslate::class)
        ->name('movies.show');

    Route::get('/metrics', MetricsController::class)->name('metrics');

    Route::view('/privacy', 'privacy')->name('privacy');
    Route::view('/terms', 'terms')->name('terms');

    Route::get('/media/poster', PosterProxyController::class)
        ->middleware('signed')
        ->name('media.poster');

    Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemaps.index');
    Route::get('/sitemaps/{type}.xml', [SitemapController::class, 'type'])
        ->where('type', '[A-Za-z0-9_-]+')
        ->name('sitemaps.type');

    Route::prefix('rss')->name('rss.')->group(function (): void {
        Route::get('/new', NewReleasesFeedController::class)->name('new');
        Route::get('/upcoming', UpcomingFeedController::class)->name('upcoming');
    });

    Route::get('/works', function () {
        $path = base_path('WORKS.md');
        abort_unless(File::exists($path), 404);

        $markdown = File::get($path);
        $content = Str::markdown($markdown);

        return view('works', ['content' => $content]);
    })->name('works');

    Route::middleware(['auth', 'noindex'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::permanentRedirect('/ctr', '/analytics/ctr')->name('ctr');
        Route::get('/ctr.svg', [CtrSvgController::class, 'line'])->name('ctr.svg');
        Route::get('/ctr/bars.svg', [CtrSvgBarsController::class, 'bars'])->name('ctr.bars.svg');
        Route::permanentRedirect('/metrics', '/analytics/queue')->name('metrics');
        Route::get('/ssr', AdminSsrController::class)->name('ssr');
        Route::get('/ssr/issues', SsrIssuesController::class)->name('ssr.issues');

        Route::resource('inquiries', AdminInquiryController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middlewareFor(['store', 'update', 'destroy'], ['admin.audit', 'admin.track'])
            ->withoutMiddlewareFor('index', ['admin.audit', 'admin.track']);
    });

    Route::prefix('flirt')->group(function (): void {
        Route::get('/', LandingPageRenderer::class)->name('landing-page');

        Route::controller(InquiryController::class)
            ->prefix('contact-us')
            ->as('contact.')
            ->group(function (): void {
                Route::get('/', 'create')->name('form');
                Route::post('/', 'store')
                    ->middleware('throttle:contact-submissions')
                    ->name('submit');
            });
    });
});
