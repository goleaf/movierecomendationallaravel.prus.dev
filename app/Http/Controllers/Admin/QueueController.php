<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\QueueStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueController extends Controller
{
    public function __construct(private readonly QueueStatisticsService $statistics) {}

    public function __invoke(Request $request): View|StreamedResponse
    {
        Gate::authorize('manageHorizonQueues');

        $metrics = $this->statistics->metrics();

        if ($request->query('format') === 'csv') {
            return $this->downloadCsv($metrics['queues'], $metrics['generated_at']->toAtomString());
        }

        return view('admin.queues', [
            'metrics' => $metrics,
        ]);
    }

    /**
     * @param  array<int, array{queue: string, in_flight: int, failed: int, average_runtime_seconds: float, jobs_per_minute: float, processed_jobs: int, batches: int}>  $rows
     */
    private function downloadCsv(array $rows, string $generatedAt): StreamedResponse
    {
        $filename = sprintf('queue-stats-%s.csv', now()->format('Ymd_His'));

        return Response::streamDownload(function () use ($rows, $generatedAt): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['generated_at', $generatedAt]);
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

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
