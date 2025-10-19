<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SsrMetric;
use App\Support\SsrMetricSample;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SsrMetricsService
{
    private ?Filesystem $disk = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $trackedPaths = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $tableColumns = null;

    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly Repository $config,
    ) {}

    public function record(Request $request, Response $response): void
    {
        if (! $this->enabled()) {
            return;
        }

        $path = $this->normalizePath($request->path());

        if (! $this->shouldTrackPath($path)) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if ($contentType === '' || ! str_contains($contentType, 'text/html')) {
            return;
        }

        $html = (string) $response->getContent();

        if ($html === '') {
            return;
        }

        $sample = $this->createSample($path, $html);
        $storedToDatabase = $this->writeToDatabase($sample);

        if (! $storedToDatabase) {
            $this->appendToJsonl($sample);
        }

        $this->updateSnapshot($sample);
    }

    /**
     * @return array{score: int, path_count: int, captured_at: Carbon|null}
     */
    public function latestSummary(): array
    {
        if ($this->canUseDatabase()) {
            /** @var object{score: float|null, path_count: int|null, captured_at: string|null}|null $row */
            $row = DB::table('ssr_metrics')
                ->selectRaw('avg(score) as score, count(distinct path) as path_count, max(created_at) as captured_at')
                ->where('created_at', '>=', Carbon::now()->subDay())
                ->first();

            $capturedAt = $row && $row->captured_at ? Carbon::parse($row->captured_at) : null;

            return [
                'score' => (int) round((float) ($row->score ?? 0)),
                'path_count' => (int) ($row->path_count ?? 0),
                'captured_at' => $capturedAt,
            ];
        }

        $paths = $this->readSnapshot();

        $scoreTotal = 0;
        $count = 0;
        $latest = null;

        foreach ($paths as $data) {
            $scoreTotal += (int) ($data['score'] ?? 0);
            $count++;

            try {
                $captured = isset($data['captured_at']) ? Carbon::parse($data['captured_at']) : null;
            } catch (Throwable) {
                $captured = null;
            }

            if ($captured && ($latest === null || $captured->greaterThan($latest))) {
                $latest = $captured;
            }
        }

        $avg = $count > 0 ? (int) round($scoreTotal / $count) : 0;

        return [
            'score' => $avg,
            'path_count' => $count,
            'captured_at' => $latest,
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    public function scoreTrend(?int $days = null): array
    {
        $days = $days ?? (int) $this->config->get('ssrmetrics.limits.trend_days', 30);

        if ($days <= 0) {
            $days = 30;
        }

        if ($this->canUseDatabase()) {
            $rows = DB::table('ssr_metrics')
                ->selectRaw('date(created_at) as d, avg(score) as s')
                ->where('created_at', '>=', Carbon::now()->subDays($days - 1))
                ->groupBy('d')
                ->orderBy('d')
                ->limit($days)
                ->get();

            $labels = [];
            $values = [];

            foreach ($rows as $row) {
                $labels[] = (string) $row->d;
                $values[] = round((float) $row->s, 2);
            }

            return [
                'labels' => $labels,
                'values' => $values,
            ];
        }

        return $this->buildTrendFromRecords($this->readArchive(), $days);
    }

    /**
     * @return array<int, array{path: string, avg_score: float, hints: array<int, string>}>
     */
    public function issues(): array
    {
        $windowDays = (int) $this->config->get('ssrmetrics.limits.issue_window_days', 2);

        if ($this->canUseDatabase()) {
            $rows = DB::table('ssr_metrics')
                ->selectRaw('path, avg(score) as avg_score, avg(blocking_scripts) as blocking, avg(ldjson_count) as ld, avg(og_count) as og, avg(img_count) as imgs, avg(size) as bytes')
                ->where('created_at', '>=', Carbon::now()->subDays($windowDays))
                ->groupBy('path')
                ->get();

            $issues = [];

            foreach ($rows as $row) {
                $issues[] = $this->formatIssue(
                    (string) $row->path,
                    (float) $row->avg_score,
                    (float) $row->blocking,
                    (float) $row->ld,
                    (float) $row->og,
                    (float) $row->imgs,
                    (float) $row->bytes,
                );
            }

            usort($issues, fn (array $a, array $b): int => $a['avg_score'] <=> $b['avg_score']);

            return $issues;
        }

        return $this->issuesFromRecords($this->readArchive(), $windowDays);
    }

    public function dropQuery(): Builder|Relation|null
    {
        if (! $this->canUseDatabase()) {
            return null;
        }

        $yesterday = Carbon::now()->subDay()->toDateString();
        $today = Carbon::now()->toDateString();

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
            ->orderBy('delta')
            ->limit(10);
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('ssrmetrics.enabled', false);
    }

    private function normalizePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '/';
        }

        $trimmed = trim($path);

        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        return '/'.ltrim($trimmed, '/');
    }

    private function shouldTrackPath(string $path): bool
    {
        $paths = $this->getTrackedPaths();

        if ($paths === []) {
            return false;
        }

        return in_array($path, $paths, true);
    }

    /**
     * @return array<int, string>
     */
    private function getTrackedPaths(): array
    {
        if ($this->trackedPaths !== null) {
            return $this->trackedPaths;
        }

        $configured = $this->config->get('ssrmetrics.paths', []);

        if (! is_array($configured)) {
            return $this->trackedPaths = [];
        }

        $paths = [];

        foreach ($configured as $key => $value) {
            if (is_array($value) && isset($value['path'])) {
                $paths[] = $this->normalizePath((string) $value['path']);
            } else {
                $paths[] = $this->normalizePath(is_int($key) ? (string) $value : (string) $key);
            }
        }

        $paths = array_values(array_unique(array_filter($paths, static fn (string $p): bool => $p !== '')));

        return $this->trackedPaths = $paths;
    }

    private function createSample(string $path, string $html): SsrMetricSample
    {
        $size = strlen($html);
        $meta = preg_match_all('/<meta\b[^>]*>/i', $html);
        $og = preg_match_all('/<meta\s+property=["\']og:/i', $html);
        $ld = preg_match_all('/<script\s+type=["\']application\/ld\+json["\']/i', $html);
        $imgs = preg_match_all('/<img\b[^>]*>/i', $html);
        $blocking = preg_match_all('/<script\b(?![^>]*defer)(?![^>]*type=["\']application\/ld\+json["\'])[^>]*>/i', $html);

        $score = $this->computeScore($size, $ld, $og, $imgs, $blocking);
        $insights = $this->collectInsights($size, $ld, $og, $imgs, $blocking, $score);

        return new SsrMetricSample(
            $path,
            $score,
            $size,
            $meta,
            $og,
            $ld,
            $imgs,
            $blocking,
            $insights,
        );
    }

    /**
     * @return array<int, string>
     */
    private function collectInsights(int $size, int $ld, int $og, int $imgs, int $blocking, int $score): array
    {
        $insights = [];
        $thresholds = $this->config->get('ssrmetrics.penalties', []);

        if ($blocking > 0) {
            $insights[] = 'blocking_scripts';
        }

        if ($ld === 0) {
            $insights[] = 'missing_ldjson';
        }

        $ogThreshold = (int) ($thresholds['og_threshold'] ?? 3);

        if ($og < $ogThreshold) {
            $insights[] = 'missing_og';
        }

        $sizeLimit = (int) ($thresholds['oversized_bytes'] ?? (900 * 1024));

        if ($size > $sizeLimit) {
            $insights[] = 'payload_size';
        }

        $imageThreshold = (int) ($thresholds['image_threshold'] ?? 60);

        if ($imgs > $imageThreshold) {
            $insights[] = 'image_volume';
        }

        if ($score < 80) {
            $insights[] = 'low_score';
        }

        return array_values(array_unique($insights));
    }

    private function computeScore(int $size, int $ld, int $og, int $imgs, int $blocking): int
    {
        $penalties = $this->config->get('ssrmetrics.penalties', []);

        $score = 100;

        $blockingPenalty = (int) ($penalties['blocking_script'] ?? 5);
        $blockingCap = (int) ($penalties['blocking_cap'] ?? 30);
        $score -= min($blockingCap, $blockingPenalty * $blocking);

        if ($ld === 0) {
            $score -= (int) ($penalties['missing_ldjson'] ?? 10);
        }

        $ogThreshold = (int) ($penalties['og_threshold'] ?? 3);

        if ($og < $ogThreshold) {
            $score -= (int) ($penalties['missing_og'] ?? 10);
        }

        $sizeLimit = (int) ($penalties['oversized_bytes'] ?? (900 * 1024));

        if ($size > $sizeLimit) {
            $score -= (int) ($penalties['oversized_penalty'] ?? 20);
        }

        $imageThreshold = (int) ($penalties['image_threshold'] ?? 60);

        if ($imgs > $imageThreshold) {
            $score -= (int) ($penalties['image_penalty'] ?? 10);
        }

        return max(0, $score);
    }

    private function writeToDatabase(SsrMetricSample $sample): bool
    {
        if (! $this->canUseDatabase()) {
            return false;
        }

        $payload = $sample->toDatabasePayload();
        $insights = $payload['insights'] ?? [];
        $columns = $this->getTableColumns();

        if (! in_array('size', $columns, true)) {
            unset($payload['size']);
        }

        if (! in_array('meta_count', $columns, true)) {
            unset($payload['meta_count']);
        }

        if (! in_array('og_count', $columns, true)) {
            unset($payload['og_count']);
        }

        if (! in_array('ldjson_count', $columns, true)) {
            unset($payload['ldjson_count']);
        }

        if (! in_array('img_count', $columns, true)) {
            unset($payload['img_count']);
        }

        if (! in_array('blocking_scripts', $columns, true)) {
            unset($payload['blocking_scripts']);
        }

        if (in_array('meta', $columns, true)) {
            $payload['meta'] = $this->encodeJson(['insights' => $insights]);
        }

        if (in_array('insights', $columns, true)) {
            $payload['insights'] = $this->encodeJson($insights);
        } else {
            unset($payload['insights']);
        }

        $now = Carbon::now();
        $payload['created_at'] = $now;

        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = $now;
        }

        try {
            DB::table('ssr_metrics')->insert($payload);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function appendToJsonl(SsrMetricSample $sample): void
    {
        $timestamp = Carbon::now()->toIso8601String();

        try {
            $this->disk()->append($this->jsonlPath(), $this->encodeJson($sample->toJsonLine($timestamp)));
        } catch (Throwable) {
            // Swallow disk errors to avoid impacting the request lifecycle.
        }
    }

    private function updateSnapshot(SsrMetricSample $sample): void
    {
        $paths = $this->readSnapshot();
        $timestamp = Carbon::now()->toIso8601String();
        $paths[$sample->path] = $sample->toSnapshotPayload($timestamp);

        uasort($paths, static fn (array $a, array $b): int => strcmp((string) ($b['captured_at'] ?? ''), (string) ($a['captured_at'] ?? '')));

        $limit = (int) $this->config->get('ssrmetrics.limits.snapshot', 50);

        if ($limit > 0 && count($paths) > $limit) {
            $paths = array_slice($paths, 0, $limit, true);
        }

        try {
            $this->disk()->put($this->snapshotPath(), $this->encodeJson(['paths' => $paths], true));
        } catch (Throwable) {
            // Ignore disk issues for snapshot writes.
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readSnapshot(): array
    {
        $path = $this->snapshotPath();

        if (! $this->disk()->exists($path)) {
            return [];
        }

        try {
            $decoded = json_decode($this->disk()->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $paths */
        $paths = $decoded['paths'] ?? [];

        return is_array($paths) ? $paths : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readArchive(): array
    {
        $path = $this->jsonlPath();

        if (! $this->disk()->exists($path)) {
            return [];
        }

        try {
            $content = $this->disk()->get($path);
        } catch (Throwable) {
            return [];
        }

        $lines = preg_split('/\r?\n/', trim((string) $content)) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));

        $limit = (int) $this->config->get('ssrmetrics.limits.jsonl_records', 200);

        if ($limit > 0 && count($lines) > $limit) {
            $lines = array_slice($lines, -$limit);
        }

        $records = [];

        foreach ($lines as $line) {
            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                $decoded = null;
            }

            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return $records;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function buildTrendFromRecords(array $records, int $days): array
    {
        if ($records === []) {
            return [
                'labels' => [],
                'values' => [],
            ];
        }

        $grouped = [];

        foreach ($records as $record) {
            $timestamp = (string) ($record['ts'] ?? $record['captured_at'] ?? '');

            if ($timestamp === '') {
                continue;
            }

            $date = substr($timestamp, 0, 10);

            if ($date === '') {
                continue;
            }

            $grouped[$date] = $grouped[$date] ?? [];
            $grouped[$date][] = (float) ($record['score'] ?? 0);
        }

        ksort($grouped);

        if ($days > 0 && count($grouped) > $days) {
            $grouped = array_slice($grouped, -$days, null, true);
        }

        $labels = array_keys($grouped);
        $values = [];

        foreach ($grouped as $scores) {
            $count = count($scores);
            $values[] = $count > 0 ? round(array_sum($scores) / $count, 2) : 0.0;
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, array{path: string, avg_score: float, hints: array<int, string>}>
     */
    private function issuesFromRecords(array $records, int $windowDays): array
    {
        if ($records === []) {
            return [];
        }

        $threshold = Carbon::now()->subDays($windowDays);
        $aggregates = [];

        foreach ($records as $record) {
            $timestamp = (string) ($record['ts'] ?? '');

            if ($timestamp === '') {
                continue;
            }

            try {
                $captured = Carbon::parse($timestamp);
            } catch (Throwable) {
                continue;
            }

            if ($captured->lessThan($threshold)) {
                continue;
            }

            $path = (string) ($record['path'] ?? '');

            if ($path === '') {
                continue;
            }

            $aggregates[$path] = $aggregates[$path] ?? [
                'score' => 0.0,
                'n' => 0,
                'blocking' => 0.0,
                'ld' => 0.0,
                'og' => 0.0,
                'imgs' => 0.0,
                'bytes' => 0.0,
            ];

            $aggregates[$path]['score'] += (float) ($record['score'] ?? 0);
            $aggregates[$path]['blocking'] += (float) ($record['blocking_scripts'] ?? $record['blocking'] ?? 0);
            $aggregates[$path]['ld'] += (float) ($record['ldjson_count'] ?? $record['ld'] ?? 0);
            $aggregates[$path]['og'] += (float) ($record['og_count'] ?? $record['og'] ?? 0);
            $aggregates[$path]['imgs'] += (float) ($record['img_count'] ?? $record['imgs'] ?? 0);
            $aggregates[$path]['bytes'] += (float) ($record['size'] ?? $record['bytes'] ?? 0);
            $aggregates[$path]['n']++;
        }

        $issues = [];

        foreach ($aggregates as $path => $aggregate) {
            $n = max(1, (int) $aggregate['n']);

            $issues[] = $this->formatIssue(
                $path,
                $aggregate['score'] / $n,
                $aggregate['blocking'] / $n,
                $aggregate['ld'] / $n,
                $aggregate['og'] / $n,
                $aggregate['imgs'] / $n,
                $aggregate['bytes'] / $n,
            );
        }

        usort($issues, fn (array $a, array $b): int => $a['avg_score'] <=> $b['avg_score']);

        return $issues;
    }

    /**
     * @return array{path: string, avg_score: float, hints: array<int, string>}
     */
    private function formatIssue(string $path, float $score, float $blocking, float $ld, float $og, float $imgs, float $bytes): array
    {
        $penalties = $this->config->get('ssrmetrics.penalties', []);
        $ogThreshold = (int) ($penalties['og_threshold'] ?? 3);
        $sizeLimit = (int) ($penalties['oversized_bytes'] ?? (900 * 1024));
        $imageThreshold = (int) ($penalties['image_threshold'] ?? 60);

        $hints = [];

        if ($blocking > 0) {
            $hints[] = __('analytics.hints.ssr.add_defer');
        }

        if ($ld === 0.0) {
            $hints[] = __('analytics.hints.ssr.add_json_ld');
        }

        if ($og < $ogThreshold) {
            $hints[] = __('analytics.hints.ssr.expand_og');
        }

        if ($bytes > $sizeLimit || $imgs > $imageThreshold || $score < 80.0) {
            $hints[] = __('analytics.hints.ssr.reduce_payload');
        }

        return [
            'path' => $path,
            'avg_score' => round($score, 2),
            'hints' => array_values(array_unique($hints)),
        ];
    }

    private function canUseDatabase(): bool
    {
        return Schema::hasTable('ssr_metrics');
    }

    /**
     * @return array<int, string>
     */
    private function getTableColumns(): array
    {
        if ($this->tableColumns !== null) {
            return $this->tableColumns;
        }

        if (! $this->canUseDatabase()) {
            return $this->tableColumns = [];
        }

        return $this->tableColumns = Schema::getColumnListing('ssr_metrics');
    }

    private function disk(): Filesystem
    {
        if ($this->disk !== null) {
            return $this->disk;
        }

        $disk = (string) $this->config->get('ssrmetrics.storage.disk', config('filesystems.default', 'local'));

        return $this->disk = $this->filesystem->disk($disk);
    }

    private function jsonlPath(): string
    {
        return (string) $this->config->get('ssrmetrics.storage.jsonl', 'metrics/ssr.jsonl');
    }

    private function snapshotPath(): string
    {
        return (string) $this->config->get('ssrmetrics.storage.snapshot', 'metrics/ssr_snapshot.json');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encodeJson(array $data, bool $pretty = false): string
    {
        $options = JSON_THROW_ON_ERROR;

        if ($pretty) {
            $options |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $options);
    }
}
