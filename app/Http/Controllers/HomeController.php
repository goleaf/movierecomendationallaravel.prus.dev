<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Services\Analytics\TrendsRollupService;
use App\Services\Recommender;
use App\Support\AnalyticsCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function __construct(
        private readonly TrendsRollupService $rollup,
        private readonly AnalyticsCache $cache,
    ) {}

    public function __invoke(Recommender $recommender): View
    {
        $did = device_id();
        $recommendation = $recommender->recommendForDevice($did, 12, 'home');
        $homeVariant = $recommendation->variant();
        $recommended = $recommendation->movies();
        if ($recommended->isEmpty()) {
            $homeVariant = 'fallback';
            $recommended = Movie::query()
                ->orderByDesc('imdb_votes')
                ->orderByDesc('imdb_rating')
                ->limit(12)
                ->get();
        }

        $trending = $this->fetchTrendingSnapshot();
        if ($trending->isEmpty()) {
            $trending = Movie::query()
                ->orderByDesc('imdb_votes')
                ->orderByDesc('imdb_rating')
                ->limit(8)
                ->get()
                ->map(fn (Movie $movie) => [
                    'movie' => $movie,
                    'clicks' => null,
                    'placement' => 'trends',
                    'variant' => 'mixed',
                ]);
        }

        return view('home.index', [
            'recommended' => $recommended,
            'homeVariant' => $homeVariant,
            'trending' => $trending,
        ]);
    }

    /**
     * @return Collection<int,array{movie:Movie,clicks:int|null,placement:string,variant:string}>
     */
    protected function fetchTrendingSnapshot(): Collection
    {
        if (! Schema::hasTable('movies')) {
            return collect();
        }

        $from = now()->copy()->subDays(7)->startOfDay();
        $to = now()->endOfDay();
        $limit = 8;

        $aggregates = $this->cache->rememberTrending('snapshot', [
            'from' => $from,
            'to' => $to,
            'limit' => $limit,
        ], function () use ($from, $to, $limit): array {
            $this->rollup->ensureBackfill($from, $to);

            if (Schema::hasTable('rec_trending_rollups')) {
                $rollupTop = DB::table('rec_trending_rollups')
                    ->selectRaw('movie_id, sum(clicks) as clicks')
                    ->whereBetween('captured_on', [$from->toDateString(), $to->toDateString()])
                    ->groupBy('movie_id')
                    ->orderByDesc('clicks')
                    ->limit($limit)
                    ->pluck('clicks', 'movie_id')
                    ->map(fn ($value) => (int) $value)
                    ->all();

                if ($rollupTop !== []) {
                    return $rollupTop;
                }
            }

            if (Schema::hasTable('rec_clicks')) {
                $clickTop = DB::table('rec_clicks')
                    ->selectRaw('movie_id, count(*) as clicks')
                    ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
                    ->groupBy('movie_id')
                    ->orderByDesc('clicks')
                    ->limit($limit)
                    ->pluck('clicks', 'movie_id')
                    ->map(fn ($value) => (int) $value)
                    ->all();

                if ($clickTop !== []) {
                    return $clickTop;
                }
            }

            return [];
        });

        $aggregates = collect($aggregates)
            ->mapWithKeys(static fn (int $clicks, string $movieId): array => [(int) $movieId => $clicks])
            ->all();

        if ($aggregates === []) {
            return collect();
        }

        $movies = Movie::query()
            ->whereIn('id', array_keys($aggregates))
            ->get()
            ->keyBy('id');

        return collect($aggregates)
            ->map(function (int $clicks, int $movieId) use ($movies) {
                $movie = $movies->get($movieId);
                if (! $movie) {
                    return null;
                }

                return [
                    'movie' => $movie,
                    'clicks' => $clicks,
                    'placement' => 'trends',
                    'variant' => 'mixed',
                ];
            })
            ->filter()
            ->values();
    }
}
