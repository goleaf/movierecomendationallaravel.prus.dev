<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Attributes\Cache;
use App\Attributes\Concerns\InteractsWithComponentAttributes;
use App\Attributes\Title;
use App\Models\Movie;
use App\Services\Analytics\TrendsRollupService;
use App\Services\Recommender;
use App\Support\AnalyticsCache;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;

#[Title('Рекомендации')]
#[Cache('home-page', ttl: 300, tags: ['pages'])]
class HomePage extends Component
{
    use InteractsWithComponentAttributes;

    public Collection $recommended;

    public Collection $trending;

    protected TrendsRollupService $rollup;

    protected AnalyticsCache $cache;

    public function boot(TrendsRollupService $rollup, AnalyticsCache $cache): void
    {
        $this->rollup = $rollup;
        $this->cache = $cache;
    }

    public function mount(Recommender $recommender): void
    {
        $hasMoviesTable = Schema::hasTable('movies');

        if ($hasMoviesTable) {
            $deviceId = $this->resolveDeviceId();

            $recommendation = $recommender->recommendForDevice($deviceId, 12);
            $this->recommended = $recommendation->movies();

            if ($this->recommended->isEmpty()) {
                $this->recommended = Movie::query()
                    ->orderByDesc('imdb_votes')
                    ->orderByDesc('imdb_rating')
                    ->limit(12)
                    ->get();
            }
        } else {
            $this->recommended = collect();
        }

        $this->trending = $this->fetchTrendingSnapshot();

        if ($this->trending->isEmpty()) {
            $this->trending = $hasMoviesTable
                ? Movie::query()
                    ->orderByDesc('imdb_votes')
                    ->orderByDesc('imdb_rating')
                    ->limit(8)
                    ->get()
                    ->map(static fn (Movie $movie) => [
                        'movie' => $movie,
                        'clicks' => null,
                    ])
                : collect();
        }
    }

    protected function resolveDeviceId(): string
    {
        $key = 'did';
        $id = (string) request()->cookie($key, '');

        if ($id === '') {
            $id = 'd_'.Str::uuid()->toString();
            Cookie::queue(Cookie::make($key, $id, 60 * 24 * 365 * 5));
        }

        return $id;
    }

    /**
     * @return Collection<int, array{movie: Movie, clicks: int|null}>
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
            ->whereIn('id', array_map('intval', array_keys($aggregates)))
            ->get()
            ->keyBy('id');

        return collect($aggregates)
            ->map(static function (int $clicks, int $movieId) use ($movies) {
                $movie = $movies->get($movieId);

                if ($movie === null) {
                    return null;
                }

                return [
                    'movie' => $movie,
                    'clicks' => $clicks,
                ];
            })
            ->filter()
            ->values();
    }

    public function render(): View
    {
        return view('livewire.home-page')->layout('layouts.app', $this->layoutData([
            'metaDescription' => 'Подборки, тренды и рекомендации',
        ]));
    }
}
