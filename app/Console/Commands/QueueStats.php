<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analytics\QueueStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class QueueStats extends Command
{
    /**
     * @var string
     */
    protected $signature = 'queue:stats {--format=table : Output format: table, json, csv} {--queue=* : Limit output to specific queue names}';

    /**
     * @var string
     */
    protected $description = 'Display queue throughput metrics.';

    public function __construct(private readonly QueueStatisticsService $statistics)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $format = strtolower((string) $this->option('format'));
        $queuesFilter = Collection::make($this->option('queue'))
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->map(fn (string $queue): string => mb_strtolower(trim($queue)))
            ->filter()
            ->values();

        $metrics = $this->statistics->metrics();

        $queues = Collection::make($metrics['queues']);

        if ($queuesFilter->isNotEmpty()) {
            $queues = $queues->filter(fn (array $row): bool => $queuesFilter->contains($row['queue']));
        }

        $queues = $queues->values()->all();

        return match ($format) {
            'json' => $this->renderJson($metrics['generated_at'], $queues, $metrics['totals']),
            'csv' => $this->renderCsv($metrics['generated_at'], $queues),
            default => $this->renderTable($metrics['generated_at'], $queues, $metrics['totals']),
        };
    }

    /**
     * @param  array<int, array{queue: string, in_flight: int, failed: int, average_runtime_seconds: float, jobs_per_minute: float, processed_jobs: int, batches: int}>  $rows
     */
    private function renderTable(CarbonImmutable $generatedAt, array $rows, array $totals): int
    {
        if ($rows === []) {
            $this->components->info(sprintf('No queue metrics available (%s).', $generatedAt->toAtomString()));

            return self::SUCCESS;
        }

        $headers = ['Queue', 'In-flight', 'Failures', 'Avg runtime (s)', 'Jobs/min', 'Processed', 'Batches'];

        $displayRows = array_map(
            fn (array $row): array => [
                $row['queue'],
                $row['in_flight'],
                $row['failed'],
                number_format($row['average_runtime_seconds'], 2, '.', ''),
                number_format($row['jobs_per_minute'], 2, '.', ''),
                $row['processed_jobs'],
                $row['batches'],
            ],
            $rows,
        );

        $displayRows[] = [
            'Total',
            $totals['in_flight'],
            $totals['failed'],
            number_format($totals['average_runtime_seconds'], 2, '.', ''),
            number_format($totals['jobs_per_minute'], 2, '.', ''),
            $totals['processed_jobs'],
            $totals['batches'],
        ];

        $this->table($headers, $displayRows);
        $this->line(sprintf('Snapshot: %s', $generatedAt->toAtomString()));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{queue: string, in_flight: int, failed: int, average_runtime_seconds: float, jobs_per_minute: float, processed_jobs: int, batches: int}>  $rows
     */
    private function renderJson(CarbonImmutable $generatedAt, array $rows, array $totals): int
    {
        $payload = [
            'generated_at' => $generatedAt->toAtomString(),
            'queues' => $rows,
            'totals' => $totals,
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{queue: string, in_flight: int, failed: int, average_runtime_seconds: float, jobs_per_minute: float, processed_jobs: int, batches: int}>  $rows
     */
    private function renderCsv(CarbonImmutable $generatedAt, array $rows): int
    {
        $handle = fopen('php://temp', 'wb+');

        if ($handle === false) {
            $this->error('Unable to initialise CSV stream.');

            return self::FAILURE;
        }

        fputcsv($handle, ['generated_at', $generatedAt->toAtomString()]);
        fputcsv($handle, ['queue', 'in_flight', 'failed', 'avg_runtime_seconds', 'jobs_per_minute', 'processed_jobs', 'batches']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['queue'],
                $row['in_flight'],
                $row['failed'],
                number_format($row['average_runtime_seconds'], 2, '.', ''),
                number_format($row['jobs_per_minute'], 2, '.', ''),
                $row['processed_jobs'],
                $row['batches'],
            ]);
        }

        rewind($handle);
        $this->output->write(stream_get_contents($handle) ?: '');
        fclose($handle);

        return self::SUCCESS;
    }
}
