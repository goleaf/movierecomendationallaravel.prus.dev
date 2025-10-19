<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SsrIssueCollection;
use App\Services\Analytics\SsrMetricsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SsrIssuesController extends Controller
{
    public function __invoke(): SsrIssueCollection
    {
        $issues = [];

        /** @var SsrMetricsService $metrics */
        $metrics = app(SsrMetricsService::class);

        if ($metrics->hasMetrics()) {
            $rows = $metrics->issuesSince(Carbon::now()->subDays(2));

            foreach ($rows as $row) {
                $advice = [];

                if ((int) $row->avg_blocking > 0) {
                    $advice[] = __('analytics.hints.ssr.add_defer');
                }

                if ((int) $row->avg_ldjson === 0) {
                    $advice[] = __('analytics.hints.ssr.add_json_ld');
                }

                if ((int) $row->avg_og < 3) {
                    $advice[] = __('analytics.hints.ssr.expand_og');
                }

                if ((float) $row->avg_score < 80) {
                    $advice[] = __('analytics.hints.ssr.reduce_payload');
                }

                $issues[] = [
                    'path' => $row->path,
                    'avg_score' => round((float) $row->avg_score, 2),
                    'hints' => $advice,
                ];
            }
        } elseif (Storage::exists('metrics/ssr.jsonl')) {
            $lines = explode("\n", Storage::get('metrics/ssr.jsonl'));
            $aggregates = [];

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);

                if (! is_array($decoded) || ! isset($decoded['path'])) {
                    continue;
                }

                $path = $decoded['path'];

                $aggregates[$path] = $aggregates[$path] ?? ['sum' => 0, 'n' => 0, 'block' => 0, 'ld' => 0, 'og' => 0];
                $aggregates[$path]['sum'] += (int) ($decoded['score'] ?? 0);
                $aggregates[$path]['n'] += 1;
                $aggregates[$path]['block'] += (int) ($decoded['blocking'] ?? 0);
                $aggregates[$path]['ld'] += (int) ($decoded['ld'] ?? 0);
                $aggregates[$path]['og'] += (int) ($decoded['og'] ?? 0);
            }

            foreach ($aggregates as $path => $aggregate) {
                $avg = $aggregate['n'] ? $aggregate['sum'] / $aggregate['n'] : 0;
                $advice = [];

                if ($aggregate['block'] > 0) {
                    $advice[] = __('analytics.hints.ssr.add_defer');
                }

                if ($aggregate['ld'] === 0) {
                    $advice[] = __('analytics.hints.ssr.missing_json_ld');
                }

                if ($aggregate['og'] < 3) {
                    $advice[] = __('analytics.hints.ssr.add_og');
                }

                $issues[] = ['path' => $path, 'avg_score' => round($avg, 2), 'hints' => $advice];
            }
        }

        usort($issues, fn ($a, $b) => $a['avg_score'] <=> $b['avg_score']);

        return new SsrIssueCollection($issues);
    }
}
