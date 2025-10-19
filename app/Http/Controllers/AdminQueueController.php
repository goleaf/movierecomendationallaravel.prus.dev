<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Analytics\QueueMetricsService;
use Illuminate\Contracts\View\View;

class AdminQueueController extends Controller
{
    public function __construct(private readonly QueueMetricsService $metrics) {}

    public function __invoke(): View
    {
        $breakdown = $this->metrics->queueBreakdown();

        return view('admin.queue', [
            'pipelines' => $breakdown['pipelines'],
            'uncategorized' => $breakdown['uncategorized'],
            'totals' => $breakdown['totals'],
        ]);
    }
}
