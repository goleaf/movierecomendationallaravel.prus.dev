<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SsrIssueCollection;
use App\Services\SsrMetricsService;

class SsrIssuesController extends Controller
{
    public function __construct(private readonly SsrMetricsService $metrics) {}

    public function __invoke(): SsrIssueCollection
    {
        return new SsrIssueCollection($this->metrics->issues());
    }
}
