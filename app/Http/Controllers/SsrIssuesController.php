<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SsrMetricsService;
use Illuminate\Http\JsonResponse;

class SsrIssuesController extends Controller
{
    public function __construct(private readonly SsrMetricsService $metrics) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'issues' => $this->metrics->issues(),
        ]);
    }
}
