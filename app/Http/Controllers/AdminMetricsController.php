<?php

namespace App\Http\Controllers;

use App\Services\Analytics\QueueMetricsService;
use Illuminate\Contracts\View\View;

class AdminMetricsController extends Controller
{
    public function __construct(private readonly QueueMetricsService $metrics)
    {
    }

    public function index(): View
    {
        $snapshot = $this->metrics->snapshot();

        return view('admin.metrics', [
            'queueCount' => $snapshot['jobs'],
            'failed' => $snapshot['failed'],
            'processed' => $snapshot['batches'],
            'horizon' => $snapshot['horizon'],
        ]);
    }
}
