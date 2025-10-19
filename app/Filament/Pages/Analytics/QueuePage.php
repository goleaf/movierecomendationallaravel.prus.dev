<?php

declare(strict_types=1);

namespace App\Filament\Pages\Analytics;

use App\Services\Analytics\QueueMetricsService;
use Filament\Pages\Page;

class QueuePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static string $view = 'filament.analytics.queue';

    protected static ?string $navigationLabel = 'Queue / Horizon';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $slug = 'queue';

    public int $jobs = 0;

    public int $failed = 0;

    public int $batches = 0;

    /** @var array{workload: array<string, string>|null, supervisors: array<int, string>|null} */
    public array $horizon = ['workload' => null, 'supervisors' => null];

    public function mount(): void
    {
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $snapshot = app(QueueMetricsService::class)->snapshot();

        $this->jobs = $snapshot['jobs'];
        $this->failed = $snapshot['failed'];
        $this->batches = $snapshot['batches'];
        $this->horizon = $snapshot['horizon'];
    }
}
