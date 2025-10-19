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

        $result = $this->trendAnalytics->getTrends($days, $type, $genre, $yf, $yt);
        $items = $result['items'];
        $period = $result['period'];
        $filters = $result['filters'];

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
