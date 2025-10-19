<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Analytics\QueuePage;
use App\Services\Analytics\QueueMetricsService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
use ReflectionClass;
use Tests\TestCase;

class AnalyticsQueuePageActionsTest extends TestCase
{
    private bool $createdEnvFile = false;

    private string $envPath;

    protected function setUp(): void
    {
        putenv('QUEUE_MANAGEMENT_ADMINS=');
        $_ENV['QUEUE_MANAGEMENT_ADMINS'] = '';
        $_SERVER['QUEUE_MANAGEMENT_ADMINS'] = '';

        $this->envPath = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'.env';

        if (! file_exists($this->envPath)) {
            file_put_contents($this->envPath, '');
            $this->createdEnvFile = true;
        }

        parent::setUp();

        app()->instance(QueueMetricsService::class, new class
        {
            /**
             * @return array{jobs: int, failed: int, batches: int, horizon: array{workload: array<string, string>|null, supervisors: array<int, string>|null}}
             */
            public function snapshot(): array
            {
                return [
                    'jobs' => 0,
                    'failed' => 0,
                    'batches' => 0,
                    'horizon' => [
                        'workload' => null,
                        'supervisors' => null,
                    ],
                ];
            }
        });

        Gate::shouldReceive('has')
            ->with('manageHorizonQueues')
            ->andReturnTrue();

        Gate::shouldReceive('allows')
            ->with('manageHorizonQueues')
            ->andReturnTrue();
    }

    protected function tearDown(): void
    {
        if ($this->createdEnvFile && file_exists($this->envPath)) {
            unlink($this->envPath);
        }

        parent::tearDown();
    }

    public function test_pause_action_without_horizon_shows_unavailable_notification(): void
    {
        session()->forget('filament.notifications');

        $page = app(QueuePage::class);

        $this->invokeQueuePageMethod($page, 'pauseHorizonQueues');

        Notification::assertNotified(__('admin.metrics.horizon.actions.unavailable'));
    }

    public function test_resume_action_without_horizon_shows_unavailable_notification(): void
    {
        session()->forget('filament.notifications');

        $page = app(QueuePage::class);

        $this->invokeQueuePageMethod($page, 'resumeHorizonQueues');

        Notification::assertNotified(__('admin.metrics.horizon.actions.unavailable'));
    }

    private function invokeQueuePageMethod(QueuePage $page, string $method): void
    {
        $reflection = new ReflectionClass($page);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        $methodReflection->invoke($page);
    }
}
