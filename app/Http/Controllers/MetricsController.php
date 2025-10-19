<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Metrics\PrometheusMetricsService;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __construct(private readonly PrometheusMetricsService $metrics) {}

    public function __invoke(): Response
    {
        $payload = $this->metrics->render();

        return response($payload, 200)
            ->header('Content-Type', 'text/plain; version=0.0.4')
            ->setCharset('');
    }
}
