<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueStatsTest extends TestCase
{
    use RefreshDatabase;

    private bool $createdEnvFile = false;

    private string $envPath;

    protected function setUp(): void
    {
        $this->envPath = dirname(__DIR__, 2).'/.env';

        if (! file_exists($this->envPath)) {
            file_put_contents($this->envPath, '');
            $this->createdEnvFile = true;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->createdEnvFile && file_exists($this->envPath)) {
            unlink($this->envPath);
        }

        parent::tearDown();
    }

    public function test_command_outputs_table_by_default(): void
    {
        $this->seedQueueData();

        Artisan::call('queue:stats');

        $output = Artisan::output();

        $this->assertStringContainsString('importers', $output);
        $this->assertStringContainsString('recommendations', $output);
        $this->assertStringContainsString('300.00', $output);
        $this->assertStringContainsString('1.80', $output);
    }

    public function test_command_exports_csv(): void
    {
        $this->seedQueueData();

        Artisan::call('queue:stats', ['--format' => 'csv']);

        $output = Artisan::output();

        $this->assertStringContainsString('queue,in_flight,failed,avg_runtime_seconds,jobs_per_minute,processed_jobs,batches', $output);
        $this->assertStringContainsString('importers,2,1,300.00,1.80,9,1', $output);
        $this->assertStringContainsString('recommendations,1,0,120.00,2.50,5,1', $output);
    }

    /**
     * Seed queue related tables with deterministic metrics.
     */
    private function seedQueueData(): void
    {
        $now = CarbonImmutable::now();
        $importerCreated = 1_000_000;
        $importerFinished = $importerCreated + 300;
        $recommendationCreated = 2_000_000;
        $recommendationFinished = $recommendationCreated + 120;

        DB::table('jobs')->insert([
            [
                'queue' => 'importers',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $importerCreated,
                'created_at' => $importerCreated,
            ],
            [
                'queue' => 'importers',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $importerCreated,
                'created_at' => $importerCreated,
            ],
            [
                'queue' => 'recommendations',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $recommendationCreated,
                'created_at' => $recommendationCreated,
            ],
        ]);

        DB::table('failed_jobs')->insert([
            [
                'uuid' => (string) Str::uuid(),
                'connection' => 'redis',
                'queue' => 'importers',
                'payload' => '{}',
                'exception' => 'Example',
                'failed_at' => $now,
            ],
        ]);

        DB::table('job_batches')->insert([
            [
                'id' => (string) Str::uuid(),
                'name' => 'importer batch',
                'total_jobs' => 10,
                'pending_jobs' => 0,
                'failed_jobs' => 1,
                'failed_job_ids' => json_encode([]),
                'options' => json_encode(['queue' => 'importers']),
                'cancelled_at' => null,
                'created_at' => $importerCreated,
                'finished_at' => $importerFinished,
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'recommendation batch',
                'total_jobs' => 5,
                'pending_jobs' => 0,
                'failed_jobs' => 0,
                'failed_job_ids' => json_encode([]),
                'options' => json_encode(['queue' => 'recommendations']),
                'cancelled_at' => null,
                'created_at' => $recommendationCreated,
                'finished_at' => $recommendationFinished,
            ],
        ]);
    }
}
