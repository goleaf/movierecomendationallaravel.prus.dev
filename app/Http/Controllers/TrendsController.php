<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TrendsRequest;
use App\Http\Resources\TrendCollection;
use App\Services\Analytics\TrendsAnalyticsService;
use Illuminate\Contracts\View\View;

class TrendsController extends Controller
{
    public function __construct(private readonly TrendsAnalyticsService $analytics) {}

    public function __invoke(TrendsRequest $request): View|TrendCollection
    {
        [
            'items' => $items,
            'filters' => $responseFilters,
            'period' => $period,
        ] = $this->analytics->getTrendsData(
            $request->days(),
            $request->type(),
            $request->genre(),
            $request->yearFrom(),
            $request->yearTo(),
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
