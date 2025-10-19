<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\QueueTimelineService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueController extends Controller
{
    public function __construct(private readonly QueueTimelineService $timeline) {}

    public function index(): View
    {
        $timeline = $this->timeline->timeline();

        return view('admin.queues', [
            'timeline' => $timeline,
        ]);
    }

    public function export(): StreamedResponse
    {
        $timeline = $this->timeline->timeline();

        return response()->streamDownload(function () use ($timeline): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['timestamp', 'jobs', 'failures']);

            foreach ($timeline['points'] as $point) {
                fputcsv($handle, [
                    $point['timestamp'],
                    $point['jobs'],
                    $point['failures'],
                ]);
            }

            fclose($handle);
        }, 'queue-metrics.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
