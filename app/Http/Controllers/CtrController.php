<?php

namespace App\Http\Controllers;

use App\Services\Analytics\CtrAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CtrController extends Controller
{
    public function __construct(private readonly CtrAnalyticsService $analytics)
    {
    }

    public function index(Request $request): View
    {
        $fromDate = $this->parseDate($request->query('from'), now()->subDays(7)->format('Y-m-d'));
        $toDate = $this->parseDate($request->query('to'), now()->format('Y-m-d'));
        $placement = $request->query('p');
        $variant = $request->query('v');

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

    private function parseDate(?string $value, string $fallback): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value ?? $fallback);
        } catch (\Throwable) {
            return CarbonImmutable::parse($fallback);
        }
    }
}
