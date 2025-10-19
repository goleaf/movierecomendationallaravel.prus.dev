<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Attributes\Cache;
use App\Attributes\Policies;
use App\Services\Analytics\QueueMetricsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

#[Policies('viewAnalyticsDashboard', 'manageHorizonQueues')]
#[Cache('analytics-queue-metrics', ttl: 120, tags: ['analytics'])]
class QueueMetrics extends Component
{
    private const DEFAULT_METRICS = [
        'queue' => 0,
        'failed' => 0,
        'processed' => 0,
        'horizon' => ['workload' => null, 'supervisors' => null],
    ];

    /** @var array{queue: int, failed: int, processed: int, horizon: array{workload: array<string, string>|null, supervisors: array<int, string>|null}} */
    public array $metrics = self::DEFAULT_METRICS;

    protected QueueMetricsService $service;

    public function boot(QueueMetricsService $service): void
    {
        $this->service = $service;
    }

    public function mount(): void
    {
        $this->refreshMetrics();
    }

    public function refreshMetrics(): void
    {
        $this->metrics = array_replace(self::DEFAULT_METRICS, $this->service->getMetrics());
    }

    public function render(): View
    {
        return view('livewire.analytics.queue-metrics');
    }
}
