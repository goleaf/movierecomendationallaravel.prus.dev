<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_pipeline_metrics(): void
    {
        $timestamp = now()->timestamp;

        DB::table('jobs')->insert([
            [
                'queue' => 'importers',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $timestamp,
                'created_at' => $timestamp,
            ],
            [
                'queue' => 'recommendations',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $timestamp,
                'created_at' => $timestamp,
            ],
            [
                'queue' => 'default',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $timestamp,
                'created_at' => $timestamp,
            ],
        ]);

        DB::table('failed_jobs')->insert([
            [
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'importers',
                'payload' => '{}',
                'exception' => 'Ingestion failure',
                'failed_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'Fallback failure',
                'failed_at' => now(),
            ],
        ]);

        $this->artisan('queue:stats')
            ->expectsTable(
                ['Queue', 'Jobs in-flight', 'Failed jobs'],
                [
                    ['Ingestion', '1', '1'],
                    ['Recommendations', '1', '0'],
                    ['Other', '1', '1'],
                ],
            )
            ->expectsOutputToContain('Total jobs in-flight')
            ->expectsOutputToContain('Total failed jobs')
            ->expectsOutputToContain('default')
            ->assertExitCode(Command::SUCCESS);
    }
}
