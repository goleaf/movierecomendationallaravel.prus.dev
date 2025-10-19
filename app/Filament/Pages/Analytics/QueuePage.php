<?php

declare(strict_types=1);

namespace App\Filament\Pages\Analytics;

use App\Services\Analytics\QueueMetricsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;

class QueuePage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static string $view = 'filament.analytics.queue';

    protected static ?string $navigationLabel = 'Queue / Horizon';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $slug = 'queue';

    private const MANAGEMENT_GATE = 'manageHorizonQueues';

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

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        if (! $this->canManageHorizonQueues() || ! $this->horizonIsAvailable()) {
            return [];
        }

        return [
            Action::make('pause-horizon')
                ->label(__('admin.metrics.horizon.actions.pause.label'))
                ->modalHeading(__('admin.metrics.horizon.actions.pause.confirm'))
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->authorize(fn (): bool => $this->canManageHorizonQueues())
                ->visible(fn (): bool => $this->horizonIsAvailable())
                ->action(function (): void {
                    $this->pauseHorizonQueues();
                }),
            Action::make('resume-horizon')
                ->label(__('admin.metrics.horizon.actions.resume.label'))
                ->modalHeading(__('admin.metrics.horizon.actions.resume.confirm'))
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->requiresConfirmation()
                ->authorize(fn (): bool => $this->canManageHorizonQueues())
                ->visible(fn (): bool => $this->horizonIsAvailable())
                ->action(function (): void {
                    $this->resumeHorizonQueues();
                }),
        ];
    }

    private function pauseHorizonQueues(): void
    {
        $this->handleHorizonAction(
            action: static function (): void {
                Horizon::pause();
            },
            successMessage: __('admin.metrics.horizon.actions.pause.success'),
        );
    }

    private function resumeHorizonQueues(): void
    {
        $this->handleHorizonAction(
            action: static function (): void {
                Horizon::continue();
            },
            successMessage: __('admin.metrics.horizon.actions.resume.success'),
        );
    }

    /**
     * @param  callable(): void  $action
     */
    private function handleHorizonAction(callable $action, string $successMessage): void
    {
        if (! $this->canManageHorizonQueues()) {
            Notification::make()
                ->title(__('admin.metrics.horizon.actions.unauthorized'))
                ->danger()
                ->send();

            return;
        }

        if (! $this->horizonIsAvailable()) {
            Notification::make()
                ->title(__('admin.metrics.horizon.actions.unavailable'))
                ->danger()
                ->send();

            return;
        }

        try {
            $action();

            Notification::make()
                ->title($successMessage)
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('admin.metrics.horizon.actions.failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->refreshData();
        }
    }

    private function horizonIsAvailable(): bool
    {
        return class_exists(Horizon::class);
    }

    private function canManageHorizonQueues(): bool
    {
        if (! Gate::has(self::MANAGEMENT_GATE)) {
            return false;
        }

        return Gate::allows(self::MANAGEMENT_GATE);
    }
}
