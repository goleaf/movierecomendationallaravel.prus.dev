<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CtrFiltersRequest;
use App\Services\Analytics\CtrAnalyticsService;
use Illuminate\Contracts\View\View;

class CtrController extends Controller
{
    public function __construct(private readonly CtrAnalyticsService $analytics) {}

    public function index(CtrFiltersRequest $request): View
    {
        $fromDate = $request->fromDate();
        $toDate = $request->toDate();
        $placement = $request->placement();
        $variant = $request->variant();

        $summary = $this->analytics->variantSummary($fromDate, $toDate, $placement, $variant);
        $funnels = $this->analytics->funnels($fromDate, $toDate);

        return view('admin.ctr', [
            'from' => $fromDate->format('Y-m-d'),
            'to' => $toDate->format('Y-m-d'),
            'placement' => $placement,
            'variant' => $variant,
            'summary' => collect($summary['summary'])->map(fn ($row) => [
                'v' => $row['variant'],
                'imps' => $row['impressions'],
                'clks' => $row['clicks'],
                'ctr' => $row['ctr'],
            ])->all(),
            'clicksP' => $summary['placementClicks'],
            'funnels' => collect($funnels)->mapWithKeys(fn ($row) => [$row['label'] => [
                'imps' => $row['imps'],
                'clks' => $row['clicks'],
                'views' => $row['views'],
            ]])->all(),
            'impVariant' => $summary['impressions'],
            'clkVariant' => $summary['clicks'],
        ])->with('funnelGenres', [])->with('funnelYears', []);
    }
}
