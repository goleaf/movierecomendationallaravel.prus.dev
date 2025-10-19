<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analytics\QueueMetricsService;
use Illuminate\Console\Command;

class QueueStats extends Command
{
    /**
     * @var string
     */
    protected $signature = 'queue:stats';

    /**
     * @var string
     */
    protected $description = 'Display queue status for ingestion and recommendation pipelines.';

    public function handle(QueueMetricsService $metrics): int
    {
        $breakdown = $metrics->queueBreakdown();
        $pipelines = $breakdown['pipelines'];

        $this->components->info('Queue status');

        $this->table(
            ['Queue', 'Jobs in-flight', 'Failed jobs'],
            [
                [
                    'Ingestion',
                    (string) ($pipelines['ingestion']['jobs'] ?? 0),
                    (string) ($pipelines['ingestion']['failed'] ?? 0),
                ],
                [
                    'Recommendations',
                    (string) ($pipelines['recommendations']['jobs'] ?? 0),
                    (string) ($pipelines['recommendations']['failed'] ?? 0),
                ],
                [
                    'Other',
                    (string) ($pipelines['other']['jobs'] ?? 0),
                    (string) ($pipelines['other']['failed'] ?? 0),
                ],
            ],
        );

        $this->newLine();
        $this->components->twoColumnDetail('Total jobs in-flight', (string) $breakdown['totals']['jobs']);
        $this->components->twoColumnDetail('Total failed jobs', (string) $breakdown['totals']['failed']);

        if ($breakdown['uncategorized'] !== []) {
            $this->newLine();
            $this->components->info('Other queues');

            $rows = [];
            foreach ($breakdown['uncategorized'] as $queueName => $totals) {
                $rows[] = [
                    $queueName,
                    (string) $totals['jobs'],
                    (string) $totals['failed'],
                ];
            }

            $this->table(['Queue', 'Jobs in-flight', 'Failed jobs'], $rows);
        }

        return self::SUCCESS;
    }
}
