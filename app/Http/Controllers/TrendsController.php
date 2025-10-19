<?php

namespace App\Http\Controllers;

use App\Services\Analytics\TrendAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrendsController extends Controller
{
    public function __construct(private TrendAnalyticsService $trendAnalytics) {}

    public function __invoke(Request $request): View|JsonResponse
    {
        $days = max(1, min(30, (int) $request->query('days', 7)));
        $type = trim((string) $request->query('type', ''));
        $genre = trim((string) $request->query('genre', ''));
        $yf = (int) $request->query('yf', 0);
        $yt = (int) $request->query('yt', 0);

        $from = now()->subDays($days)->format('Y-m-d 00:00:00');
        $to = now()->format('Y-m-d 23:59:59');

        $items = collect();
        if (Schema::hasTable('rec_clicks')) {
            $query = DB::table('rec_clicks')
                ->join('movies', 'movies.id', '=', 'rec_clicks.movie_id')
                ->selectRaw('movies.id, movies.title, movies.poster_url, movies.year, movies.type, movies.imdb_rating, movies.imdb_votes, count(*) as clicks')
                ->whereBetween('rec_clicks.created_at', [$from, $to])
                ->groupBy('movies.id', 'movies.title', 'movies.poster_url', 'movies.year', 'movies.type', 'movies.imdb_rating', 'movies.imdb_votes')
                ->orderByDesc('clicks');

            if ($type !== '') {
                $query->where('movies.type', $type);
            }
            if ($genre !== '') {
                $query->whereJsonContains('movies.genres', $genre);
            }
            if ($yf > 0) {
                $query->where('movies.year', '>=', $yf);
            }
            if ($yt > 0) {
                $query->where('movies.year', '<=', $yt);
            }

            $items = $query
                ->limit(40)
                ->get()
                ->map(function ($item) {
                    $item->placement = 'trends';
                    $item->variant = 'mixed';

                    return $item;
                });
        }

        if ($items->isEmpty()) {
            $fallback = Movie::query()
                ->when($type !== '', fn ($q) => $q->where('type', $type))
                ->when($genre !== '', fn ($q) => $q->whereJsonContains('genres', $genre))
                ->when($yf > 0, fn ($q) => $q->where('year', '>=', $yf))
                ->when($yt > 0, fn ($q) => $q->where('year', '<=', $yt))
                ->orderByDesc('imdb_votes')
                ->orderByDesc('imdb_rating')
                ->limit(40)
                ->get();

            $items = $fallback->map(fn (Movie $movie) => (object) [
                'id' => $movie->id,
                'title' => $movie->title,
                'poster_url' => $movie->poster_url,
                'year' => $movie->year,
                'type' => $movie->type,
                'imdb_rating' => $movie->imdb_rating,
                'imdb_votes' => $movie->imdb_votes,
                'clicks' => null,
                'placement' => 'trends',
                'variant' => 'mixed',
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'days' => $period['days'],
                'type' => $filters['type'],
                'genre' => $filters['genre'],
                'yf' => $filters['year_from'],
                'yt' => $filters['year_to'],
                'items' => $items,
            ]);
        }

        return view('trends.index', [
            'days' => $period['days'],
            'type' => $filters['type'],
            'genre' => $filters['genre'],
            'yf' => $filters['year_from'],
            'yt' => $filters['year_to'],
            'items' => $items,
            'from' => $period['from'],
            'to' => $period['to'],
        ]);
    }
}
