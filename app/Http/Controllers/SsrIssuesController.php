<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SsrIssueCollection;
use App\Services\Analytics\SsrMetricsService;

class SsrIssuesController extends Controller
{
    public function __construct(private readonly SsrMetricsService $ssrMetricsService) {}

    public function __invoke(): SsrIssueCollection
    {
        $issues = [];

        foreach ($this->ssrMetricsService->aggregateIssues() as $row) {
            $advice = [];

            if ((int) round($row['avg_block']) > 0) {
                $advice[] = __('analytics.hints.ssr.add_defer');
            }

            if ((int) round($row['ld']) === 0) {
                $advice[] = __('analytics.hints.ssr.add_json_ld');
            }

            if ((int) round($row['og']) < 3) {
                $advice[] = __('analytics.hints.ssr.expand_og');
            }

            if ((float) $row['avg_score'] < 80) {
                $advice[] = __('analytics.hints.ssr.reduce_payload');
            }

            $issues[] = [
                'path' => $row['path'],
                'avg_score' => round((float) $row['avg_score'], 2),
                'hints' => $advice,
            ];
        }

        usort($issues, fn ($a, $b) => $a['avg_score'] <=> $b['avg_score']);

        return new SsrIssueCollection($issues);
    }
}
