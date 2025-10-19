<?php

declare(strict_types=1);

namespace App\Filament\Pages\Analytics;

use App\Services\Analytics\QueueMetricsService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
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

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('pauseQueue')
                ->label(__('admin.metrics.actions.pause'))
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('admin.metrics.actions.confirm_pause'))
                ->visible(fn (): bool => $this->canManageQueues())
                ->action(function (): void {
                    if (! $this->canManageQueues()) {
                        return;
                    }

                    if (! $this->callHorizon('pause')) {
                        return;
                    }

                    $this->refreshData();

                    $this->notifySuccess(__('admin.metrics.actions.paused'));
                }),
            Action::make('resumeQueue')
                ->label(__('admin.metrics.actions.resume'))
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('admin.metrics.actions.confirm_resume'))
                ->visible(fn (): bool => $this->canManageQueues())
                ->action(function (): void {
                    if (! $this->canManageQueues()) {
                        return;
                    }

                    if (! $this->callHorizon('continue')) {
                        return;
                    }

                    $this->refreshData();

                    $this->notifySuccess(__('admin.metrics.actions.resumed'));
                }),
        ];
    }

    private function canManageQueues(): bool
    {
        $user = Filament::auth()->user();

        if ($user === null || ! method_exists($user, 'can')) {
            return false;
        }

        return $user->can('manageHorizon');
    }

    private function callHorizon(string $method): bool
    {
        $horizonClass = \Laravel\Horizon\Horizon::class;

        if (! class_exists($horizonClass) || ! method_exists($horizonClass, $method)) {
            $this->notifyUnavailable();

            return false;
        }

        forward_static_call([$horizonClass, $method]);

        return true;
    }

    private function notifySuccess(string $message): void
    {
        Notification::make()
            ->title($message)
            ->success()
            ->send();
    }

    private function notifyUnavailable(): void
    {
        Notification::make()
            ->title(__('admin.metrics.actions.unavailable'))
            ->danger()
            ->send();
    }
}
