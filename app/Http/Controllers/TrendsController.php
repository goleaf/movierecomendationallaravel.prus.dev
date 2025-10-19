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
        $days = $request->days();
        $type = $request->type();
        $genre = $request->genre();
        $yf = $request->yearFrom();
        $yt = $request->yearTo();

        [
            'items' => $items,
            'filters' => $filters,
            'period' => $period,
        ] = $this->analytics->getTrendsData($days, $type, $genre, $yf, $yt);

        if ($request->wantsJson()) {
            return (new TrendCollection($items))->additional([
                'filters' => $filters,
                'period' => $period,
            ]);
        }

        return view('trends.index', [
            'filters' => $filters,
            'period' => $period,
            'items' => $items,
        ]);
    }
}
