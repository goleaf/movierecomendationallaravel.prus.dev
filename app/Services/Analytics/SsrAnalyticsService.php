<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Services\SsrMetricsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class SsrAnalyticsService
{
    public function __construct(private readonly SsrMetricsService $metrics) {}

    /**
     * @return array{label: string, score: int, paths: int, description: string}
     */
    public function headline(): array
    {
        return $this->metrics->headline();
    }

    /**
     * @return array{datasets: array<int, array{label: string, data: array<int, float>}>, labels: array<int, string>}
     */
    public function trend(int $limit = 30): array
    {
        return $this->metrics->trend($limit);
    }

    public function dropQuery(): Builder|Relation|null
    {
        return $this->metrics->dropQuery();
    }

    /**
     * @return array<int, array{path: string, score_today: float, score_yesterday: float, delta: float}>
     */
    public function dropRows(int $limit = 10): array
    {
        return $this->metrics->dropRows($limit);
    }
}
