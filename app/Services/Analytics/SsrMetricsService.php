<?php

declare(strict_types=1);

namespace App\Services\Analytics;

class SsrMetricsService
{
    public function __construct(
        private readonly SsrAnalyticsService $analytics,
    ) {}

    /**
     * @return array{label: string, score: int, paths: int, description: string}
     */
    public function headline(): array
    {
        return $this->analytics->headline();
    }

    /**
     * @return array{datasets: array<int, array{label: string, data: array<int, float>}>, labels: array<int, string>}
     */
    public function trend(int $limit = 30): array
    {
        return $this->analytics->trend($limit);
    }

    /**
     * @return array<int, array{path: string, score_today: float, score_yesterday: float, delta: float}>
     */
    public function dropRows(int $limit = 10): array
    {
        return $this->analytics->dropRows($limit);
    }
}
