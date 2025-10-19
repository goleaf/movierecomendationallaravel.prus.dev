<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TrendsFiltersRequest;
use App\Services\Analytics\TrendsAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class TrendsController extends Controller
{
    public function __construct(private readonly TrendsAnalyticsService $analytics) {}

    public function __invoke(TrendsFiltersRequest $request): View|JsonResponse
    {
        $filters = $request->filters();

        [
            'items' => $items,
            'filters' => $resolvedFilters,
            'period' => $period,
        ] = $this->analytics->getTrendsData(
            $filters['days'],
            $filters['type'],
            $filters['genre'],
            $filters['year_from'],
            $filters['year_to'],
        );

        if ($request->wantsJson()) {
            return response()->json([
                'filters' => $resolvedFilters,
                'period' => $period,
                'items' => $items,
            ]);
        }

        return view('trends.index', [
            'filters' => $resolvedFilters,
            'period' => $period,
            'items' => $items,
        ]);
    }
}
