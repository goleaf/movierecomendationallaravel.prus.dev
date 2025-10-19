<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SsrMetricsService;
use Illuminate\Contracts\View\View;

class SsrDashboardController extends Controller
{
    public function __construct(private readonly SsrMetricsService $metrics) {}

    public function __invoke(): View
    {
        return view('admin.ssr', [
            'summary' => $this->metrics->latestSummary(),
            'trend' => $this->metrics->scoreTrend(14),
            'issues' => $this->metrics->issues(),
        ]);
    }
}
