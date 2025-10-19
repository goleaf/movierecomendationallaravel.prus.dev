<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SsrIssueCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SsrIssuesController extends Controller
{
    public function __invoke(): SsrIssueCollection
    {
        $issues = [];
        if (Schema::hasTable('ssr_metrics')) {
            $timestampColumn = Schema::hasColumn('ssr_metrics', 'recorded_at') ? 'recorded_at' : 'created_at';

            if (Schema::hasColumn('ssr_metrics', 'payload')) {
                $blockingExpression = "avg(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '\$.counts.blocking_scripts')) AS DECIMAL(10, 2)), 0)) as avg_block";
                $ldExpression = "avg(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '\$.counts.ldjson')) AS DECIMAL(10, 2)), 0)) as ld";
                $ogExpression = "avg(COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '\$.counts.og')) AS DECIMAL(10, 2)), 0)) as og";

                $rows = DB::table('ssr_metrics')
                    ->selectRaw("path, avg(score) as avg_score, {$blockingExpression}, {$ldExpression}, {$ogExpression}")
                    ->where($timestampColumn, '>=', now()->subDays(2))
                    ->groupBy('path')
                    ->get();
            } else {
                $rows = DB::table('ssr_metrics')
                    ->selectRaw('path, avg(score) as avg_score, avg(blocking_scripts) as avg_block, avg(ldjson_count) as ld, avg(og_count) as og')
                    ->where($timestampColumn, '>=', now()->subDays(2))
                    ->groupBy('path')
                    ->get();
            }
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
        } elseif (Storage::exists('metrics/ssr.jsonl')) {
            $lines = explode("\n", Storage::get('metrics/ssr.jsonl'));
            $agg = [];
            foreach ($lines as $ln) {
                $ln = trim($ln);
                if ($ln === '') {
                    continue;
                }
                $j = json_decode($ln, true);
                if (! $j) {
                    continue;
                }
                $p = $j['path'];
                $agg[$p] = $agg[$p] ?? ['sum' => 0, 'n' => 0, 'block' => 0, 'ld' => 0, 'og' => 0];
                $agg[$p]['sum'] += (int) ($j['score'] ?? 0);
                $agg[$p]['n'] += 1;
                $agg[$p]['block'] += (int) ($j['blocking'] ?? 0);
                $agg[$p]['ld'] += (int) ($j['ld'] ?? 0);
                $agg[$p]['og'] += (int) ($j['og'] ?? 0);
            }
            foreach ($agg as $p => $a) {
                $avg = $a['n'] ? $a['sum'] / $a['n'] : 0;
                $advice = [];
                if ($a['block'] > 0) {
                    $advice[] = __('analytics.hints.ssr.add_defer');
                }
                if ($a['ld'] == 0) {
                    $advice[] = __('analytics.hints.ssr.missing_json_ld');
                }
                if ($a['og'] < 3) {
                    $advice[] = __('analytics.hints.ssr.add_og');
                }
                $issues[] = ['path' => $p, 'avg_score' => round($avg, 2), 'hints' => $advice];
            }
        }
        usort($issues, fn ($a, $b) => $a['avg_score'] <=> $b['avg_score']);

        return new SsrIssueCollection($issues);
    }
}
