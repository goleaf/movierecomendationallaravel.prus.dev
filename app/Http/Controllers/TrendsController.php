<?php

namespace App\Http\Controllers;

use App\Services\Analytics\TrendsAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrendsController extends Controller
{
    public function __construct(private readonly TrendsAnalyticsService $analytics)
    {
    }

    public function __invoke(Request $request): View|JsonResponse
    {
        $days = max(1, min(30, (int) $request->query('days', 7)));
        $type = trim((string) $request->query('type', ''));
        $genre = trim((string) $request->query('genre', ''));
        $yf = (int) $request->query('yf', 0);
        $yt = (int) $request->query('yt', 0);

        $items = $this->analytics->trending($days, $type, $genre, $yf, $yt);

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

        $from = now()->subDays($days)->format('Y-m-d 00:00:00');
        $to = now()->format('Y-m-d 23:59:59');

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
