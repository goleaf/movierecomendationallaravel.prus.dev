<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TrendsFiltersRequest;
use App\Http\Resources\TrendCollection;
use App\Services\Analytics\TrendsAnalyticsService;
use Illuminate\Contracts\View\View;

class TrendsController extends Controller
{
    public function __construct(private readonly TrendsAnalyticsService $analytics) {}

    public function __invoke(TrendsFiltersRequest $request): View|TrendCollection
    {
        $filters = $request->filters();

        [
            'items' => $items,
            'filters' => $responseFilters,
            'period' => $period,
        ] = $this->analytics->getTrendsData(
            $filters['days'],
            $filters['type'],
            $filters['genre'],
            $filters['yf'] ?? 0,
            $filters['yt'] ?? 0,
        );

        if ($request->wantsJson()) {
            return (new TrendCollection($items))->additional([
                'filters' => $responseFilters,
                'period' => $period,
            ]);
        }

        return view('trends.index', [
            'filters' => $responseFilters,
            'period' => $period,
            'items' => $items,
        ]);
    }
}
