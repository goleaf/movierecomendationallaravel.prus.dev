<?php

namespace App\Http\Controllers;

use App\Services\Analytics\TrendsAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrendsController extends Controller
{
    public function __construct(private readonly TrendsAnalyticsService $analytics) {}

    public function __invoke(Request $request): View|JsonResponse
    {
        $days = max(1, min(30, (int) $request->query('days', 7)));
        $type = trim((string) $request->query('type', ''));
        $genre = trim((string) $request->query('genre', ''));
        $yf = (int) $request->query('yf', 0);
        $yt = (int) $request->query('yt', 0);

        [
            'items' => $items,
            'filters' => $filters,
            'period' => $period,
        ] = $this->analytics->getTrendsData($days, $type, $genre, $yf, $yt);

        if ($request->wantsJson()) {
            return response()->json([
                'filters' => $filters,
                'period' => $period,
                'items' => $items,
            ]);
        }

        return view('trends.index', [
            'filters' => $filters,
            'period' => $period,
            'items' => $items,
        ]);
    }
}
