<?php

namespace App\Livewire\Analytics;

use App\Services\Analytics\QueueMetricsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class QueueMetrics extends Component
{
    /** @var array{queue: int, failed: int, processed: int, horizon: array{workload: array<string, string>|null, supervisors: array<int, string>|null}} */
    public array $metrics = [
        'queue' => 0,
        'failed' => 0,
        'processed' => 0,
        'horizon' => ['workload' => null, 'supervisors' => null],
    ];

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
        $this->metrics = $this->service->getMetrics();
    }

    public function render(): View
    {
        return view('livewire.analytics.queue-metrics');
    }
}
