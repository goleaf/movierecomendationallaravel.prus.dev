<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SsrIssueCollection;
use App\Support\SsrMetricsStorage;
use Illuminate\Support\Facades\DB;

class SsrIssuesController extends Controller
{
    public function __invoke(): SsrIssueCollection
    {
        $issues = [];
        if (\Schema::hasTable('ssr_metrics')) {
            $timestampColumn = \Schema::hasColumn('ssr_metrics', 'collected_at') ? 'collected_at' : 'created_at';

            $rows = DB::table('ssr_metrics')
                ->selectRaw('path, avg(score) as avg_score, avg(blocking_scripts) as avg_block, avg(ldjson_count) as ld, avg(og_count) as og')
                ->whereNotNull($timestampColumn)
                ->where($timestampColumn, '>=', now()->subDays(2)->toDateTimeString())
                ->groupBy('path')
                ->get();
            foreach ($rows as $r) {
                $advice = [];
                if ((int) $r->avg_block > 0) {
                    $advice[] = __('analytics.hints.ssr.add_defer');
                }
                if ((int) $r->ld === 0) {
                    $advice[] = __('analytics.hints.ssr.add_json_ld');
                }
                if ((int) $r->og < 3) {
                    $advice[] = __('analytics.hints.ssr.expand_og');
                }
                if ((float) $r->avg_score < 80) {
                    $advice[] = __('analytics.hints.ssr.reduce_payload');
                }
                $issues[] = ['path' => $r->path, 'avg_score' => round((float) $r->avg_score, 2), 'hints' => $advice];
            }
        } else {
            $records = SsrMetricsStorage::readJsonl();

            if ($records !== []) {
                $agg = [];

                foreach ($records as $record) {
                    if (! isset($record['path'])) {
                        continue;
                    }

                    $path = (string) $record['path'];

                    if ($path === '') {
                        continue;
                    }

                    $sum = $agg[$path]['sum'] ?? 0;
                    $count = $agg[$path]['n'] ?? 0;
                    $block = $agg[$path]['block'] ?? 0;
                    $ld = $agg[$path]['ld'] ?? 0;
                    $og = $agg[$path]['og'] ?? 0;

                    $agg[$path] = [
                        'sum' => $sum + (int) ($record['score'] ?? 0),
                        'n' => $count + 1,
                        'block' => $block + (int) ($record['blocking_scripts'] ?? $record['blocking'] ?? 0),
                        'ld' => $ld + (int) ($record['ldjson_count'] ?? $record['ld'] ?? 0),
                        'og' => $og + (int) ($record['og_count'] ?? $record['og'] ?? 0),
                    ];
                }

                foreach ($agg as $path => $values) {
                    $avg = $values['n'] ? $values['sum'] / $values['n'] : 0;
                    $advice = [];

                    if ($values['block'] > 0) {
                        $advice[] = __('analytics.hints.ssr.add_defer');
                    }

                    if ($values['ld'] === 0) {
                        $advice[] = __('analytics.hints.ssr.missing_json_ld');
                    }

                    if ($values['og'] < 3) {
                        $advice[] = __('analytics.hints.ssr.add_og');
                    }

                    $issues[] = [
                        'path' => $path,
                        'avg_score' => round($avg, 2),
                        'hints' => $advice,
                    ];
                }
            }
        }
        usort($issues, fn ($a, $b) => $a['avg_score'] <=> $b['avg_score']);

        return new SsrIssueCollection($issues);
    }
}
