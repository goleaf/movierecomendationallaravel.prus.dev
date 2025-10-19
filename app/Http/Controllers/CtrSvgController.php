<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSvgCaching;
use App\Http\Requests\CtrRangeRequest;
use App\Services\Analytics\CtrAnalyticsService;
use Illuminate\Http\Response;

class CtrSvgController extends Controller
{
    use HandlesSvgCaching;

    public function __construct(private readonly CtrAnalyticsService $analytics) {}

    public function line(CtrRangeRequest $request): Response
    {
        $from = $request->fromDate(now()->subDays(14)->toImmutable());
        $to = $request->toDate(now()->toImmutable());

        $svg = $this->analytics->buildDailyCtrSvg($from, $to) ?? $this->emptyChart();

        return $this->cachedSvgResponse($request, $svg, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ], ['rec_ab_logs', 'rec_clicks']);
    }

    private function emptyChart(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="720" height="260">'
            .'<rect x="0" y="0" width="720" height="260" fill="#0b0c0f"/>'
            .'<text x="50%" y="50%" fill="#889" font-size="14" dominant-baseline="middle" text-anchor="middle">No data</text>'
            .'</svg>';
    }
}
