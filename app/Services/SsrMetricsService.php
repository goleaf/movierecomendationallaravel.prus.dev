<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\StoreSsrMetric;
use App\Models\SsrMetric;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SsrMetricsService
{
    public function capture(Request $request, Response $response, float $startedAt): void
    {
        if (! config('ssrmetrics.enabled')) {
            return;
        }

        $path = '/'.ltrim($request->path(), '/');

        if (! collect(config('ssrmetrics.paths', []))->contains($path)) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if ($contentType === '' || ! str_contains($contentType, 'text/html')) {
            return;
        }

        $firstByteMs = (int) round((microtime(true) - $startedAt) * 1000);
        $html = $response->getContent() ?? '';
        $payload = $this->buildPayload($path, $html, $firstByteMs);

        StoreSsrMetric::dispatch($payload);
    }

    /**
     * @return array{score:int, paths:int}
     */
    public function getLatestScoreSummary(): array
    {
        if (Schema::hasTable('ssr_metrics')) {
            $row = DB::table('ssr_metrics')->orderByDesc('id')->first();

            if ($row !== null) {
                return [
                    'score' => (int) $row->score,
                    'paths' => 1,
                ];
            }

            return [
                'score' => 0,
                'paths' => 0,
            ];
        }

        if (! Storage::exists('metrics/last.json')) {
            return [
                'score' => 0,
                'paths' => 0,
            ];
        }

        $json = json_decode(Storage::get('metrics/last.json'), true) ?: [];
        $paths = count($json);

        if ($paths === 0) {
            return [
                'score' => 0,
                'paths' => 0,
            ];
        }

        $score = array_reduce($json, static function (int $carry, array $row): int {
            return $carry + (int) ($row['score'] ?? 0);
        }, 0);

        return [
            'score' => (int) round($score / $paths),
            'paths' => $paths,
        ];
    }

    /**
     * @return array{labels:array<int, string>, series:array<int, float>}
     */
    public function getScoreTrend(int $limit = 30): array
    {
        if (Schema::hasTable('ssr_metrics')) {
            $rows = DB::table('ssr_metrics')
                ->selectRaw('date(created_at) as d, avg(score) as s')
                ->groupBy('d')
                ->orderBy('d')
                ->limit($limit)
                ->get();

            return $this->formatTrendFromCollection($rows);
        }

        if (! Storage::exists('metrics/last.json')) {
            return [
                'labels' => [],
                'series' => [],
            ];
        }

        $json = json_decode(Storage::get('metrics/last.json'), true) ?: [];

        if ($json === []) {
            return [
                'labels' => [],
                'series' => [],
            ];
        }

        $avg = 0;
        $count = 0;

        foreach ($json as $row) {
            $avg += (int) ($row['score'] ?? 0);
            $count++;
        }

        return [
            'labels' => [now()->toDateString()],
            'series' => [$count > 0 ? round($avg / $count, 2) : 0.0],
        ];
    }

    public function getDropQuery(): EloquentBuilder|Relation|null
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return null;
        }

        $yesterday = now()->subDay()->toDateString();
        $today = now()->toDateString();

        return SsrMetric::query()
            ->fromSub(function ($query) use ($today, $yesterday): void {
                $query
                    ->fromSub(function ($aggregateQuery) use ($today, $yesterday): void {
                        $aggregateQuery
                            ->from('ssr_metrics')
                            ->selectRaw('path, date(created_at) as d, avg(score) as avg_score')
                            ->whereIn(DB::raw('date(created_at)'), [$yesterday, $today])
                            ->groupBy('path', 'd');
                    }, 'agg')
                    ->selectRaw(
                        'path,
                        max(case when d = ? then avg_score end) as score_today,
                        max(case when d = ? then avg_score end) as score_yesterday',
                        [$today, $yesterday]
                    )
                    ->groupBy('path');
            }, 'pivot')
            ->select([
                DB::raw('row_number() over (order by coalesce(score_today, 0) - coalesce(score_yesterday, 0), path) as id'),
                'path',
                DB::raw('coalesce(score_today, 0) as score_today'),
                DB::raw('coalesce(score_yesterday, 0) as score_yesterday'),
                DB::raw('coalesce(score_today, 0) - coalesce(score_yesterday, 0) as delta'),
            ])
            ->whereRaw('(coalesce(score_today, 0) - coalesce(score_yesterday, 0)) < 0')
            ->orderBy('delta');
    }

    /**
     * @return array<int, array{path:string, avg_score:float, hints:array<int, string>}>
     */
    public function getIssues(): array
    {
        if (Schema::hasTable('ssr_metrics')) {
            $rows = DB::table('ssr_metrics')
                ->selectRaw('path, avg(score) as avg_score, avg(blocking_scripts) as avg_block, avg(ldjson_count) as ld, avg(og_count) as og')
                ->where('created_at', '>=', now()->subDays(2))
                ->groupBy('path')
                ->get();

            $issues = [];

            foreach ($rows as $row) {
                $issues[] = $this->formatIssue(
                    (string) $row->path,
                    (float) $row->avg_score,
                    (float) $row->avg_block,
                    (float) $row->ld,
                    (float) $row->og,
                    true
                );
            }

            usort($issues, static fn (array $a, array $b): int => $a['avg_score'] <=> $b['avg_score']);

            return $issues;
        }

        if (! Storage::exists('metrics/ssr.jsonl')) {
            return [];
        }

        $lines = explode("\n", Storage::get('metrics/ssr.jsonl'));
        $aggregated = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($line, true);

            if ($decoded === null) {
                continue;
            }

            $path = (string) ($decoded['path'] ?? '');

            if ($path === '') {
                continue;
            }

            $aggregated[$path] = $aggregated[$path] ?? ['sum' => 0, 'count' => 0, 'blocking' => 0, 'ld' => 0, 'og' => 0];
            $aggregated[$path]['sum'] += (int) ($decoded['score'] ?? 0);
            $aggregated[$path]['count'] += 1;
            $aggregated[$path]['blocking'] += (int) ($decoded['blocking'] ?? 0);
            $aggregated[$path]['ld'] += (int) ($decoded['ld'] ?? 0);
            $aggregated[$path]['og'] += (int) ($decoded['og'] ?? 0);
        }

        $issues = [];

        foreach ($aggregated as $path => $data) {
            $count = $data['count'] > 0 ? $data['count'] : 1;
            $issues[] = $this->formatIssue(
                (string) $path,
                (float) $data['sum'] / $count,
                (float) $data['blocking'],
                (float) $data['ld'],
                (float) $data['og'],
                false
            );
        }

        usort($issues, static fn (array $a, array $b): int => $a['avg_score'] <=> $b['avg_score']);

        return $issues;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{labels:array<int, string>, series:array<int, float>}
     */
    private function formatTrendFromCollection(Collection $rows): array
    {
        $labels = [];
        $series = [];

        foreach ($rows as $row) {
            $labels[] = (string) $row->d;
            $series[] = round((float) $row->s, 2);
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $path, string $html, int $firstByteMs): array
    {
        $htmlSize = strlen($html);
        $metaCount = preg_match_all('/<meta\b[^>]*>/i', $html);
        $ogCount = preg_match_all('/<meta\s+property=["\']og:/i', $html);
        $ldjsonCount = preg_match_all('/<script\s+type=["\']application\/ld\+json["\']/i', $html);
        $imgCount = preg_match_all('/<img\b[^>]*>/i', $html);
        $blockingScripts = preg_match_all('/<script\b(?![^>]*defer)(?![^>]*type=["\']application\/ld\+json["\'])[^>]*>/i', $html);

        $score = 100;

        if ($blockingScripts > 0) {
            $score -= min(30, 5 * $blockingScripts);
        }

        if ($ldjsonCount === 0) {
            $score -= 10;
        }

        if ($ogCount < 3) {
            $score -= 10;
        }

        if ($htmlSize > 900 * 1024) {
            $score -= 20;
        }

        if ($imgCount > 60) {
            $score -= 10;
        }

        $score = max(0, $score);

        $metaPayload = [
            'first_byte_ms' => $firstByteMs,
            'html_size' => $htmlSize,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => $imgCount,
            'blocking_scripts' => $blockingScripts,
            'has_json_ld' => $ldjsonCount > 0,
            'has_open_graph' => $ogCount > 0,
        ];

        return [
            'path' => $path,
            'score' => $score,
            'html_size' => $htmlSize,
            'meta_count' => $metaCount,
            'og_count' => $ogCount,
            'ldjson_count' => $ldjsonCount,
            'img_count' => $imgCount,
            'blocking_scripts' => $blockingScripts,
            'first_byte_ms' => $firstByteMs,
            'meta' => $metaPayload,
        ];
    }

    /**
     * @return array{path:string, avg_score:float, hints:array<int, string>}
     */
    private function formatIssue(string $path, float $averageScore, float $blocking, float $ld, float $og, bool $averagesProvided): array
    {
        $hints = [];

        if ($blocking > 0) {
            $hints[] = __('analytics.hints.ssr.add_defer');
        }

        if ($ld === 0.0) {
            $hints[] = $averagesProvided ? __('analytics.hints.ssr.add_json_ld') : __('analytics.hints.ssr.missing_json_ld');
        }

        if ($og < 3) {
            $hints[] = $averagesProvided ? __('analytics.hints.ssr.expand_og') : __('analytics.hints.ssr.add_og');
        }

        if ($averageScore < 80) {
            $hints[] = __('analytics.hints.ssr.reduce_payload');
        }

        return [
            'path' => $path,
            'avg_score' => round($averageScore, 2),
            'hints' => $hints,
        ];
    }
}
