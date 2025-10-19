<?php

namespace App\Http\Controllers;

use App\Services\Analytics\CtrAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CtrSvgController extends Controller
{
    public function __construct(private readonly CtrAnalyticsService $analytics)
    {
    }

    public function line(Request $request): Response
    {
        $from = $this->parseDate($request->query('from'), now()->subDays(14)->format('Y-m-d'));
        $to = $this->parseDate($request->query('to'), now()->format('Y-m-d'));

        $svg = $this->analytics->buildDailyCtrSvg($from, $to) ?? $this->emptyChart();

        return response($svg)->header('Content-Type', 'image/svg+xml');
    }

    private function parseDate(?string $value, string $fallback): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value ?? $fallback);
        } catch (\Throwable) {
            return CarbonImmutable::parse($fallback);
        }
    }

    private function emptyChart(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="720" height="260">'
            .'<rect x="0" y="0" width="720" height="260" fill="#0b0c0f"/>'
            .'<text x="50%" y="50%" fill="#889" font-size="14" dominant-baseline="middle" text-anchor="middle">No data</text>'
            .'</svg>';
    }
}
