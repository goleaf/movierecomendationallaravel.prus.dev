<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PrometheusMetricsService;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __construct(private readonly PrometheusMetricsService $metrics) {}

    public function __invoke(): Response
    {
        return response(
            $this->metrics->render(),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; version=0.0.4'],
        );
    }
}
