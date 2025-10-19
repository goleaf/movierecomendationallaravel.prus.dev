<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CtrBarsRequest;
use App\Services\Analytics\CtrAnalyticsService;
use Illuminate\Http\Response;

class CtrSvgBarsController extends Controller
{
    public function __construct(private readonly CtrAnalyticsService $analytics) {}

    public function bars(CtrBarsRequest $request): Response
    {
        $from = $request->fromDate();
        $to = $request->toDate();

        $svg = $this->analytics->buildPlacementCtrSvg($from, $to) ?? $this->emptyChart();

        return $this->svgResponse($svg);
    }

    private function emptyChart(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="720" height="260">'
            .'<rect x="0" y="0" width="720" height="260" fill="#0b0c0f"/>'
            .'<text x="50%" y="50%" fill="#889" font-size="14" dominant-baseline="middle" text-anchor="middle">No data</text>'
            .'</svg>';
    }

    private function svgResponse(string $svg): Response
    {
        return new Response($svg, Response::HTTP_OK, ['Content-Type' => 'image/svg+xml']);
    }
}
