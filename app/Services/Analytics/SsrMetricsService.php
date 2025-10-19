<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Jobs\StoreSsrMetric;
use App\Models\SsrMetric;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SsrMetricsService
{
    public function __construct(private readonly Repository $config) {}

    /**
     * @return array<string, mixed>
     */
    public function buildMetricPayload(string $path, string $html, int $firstByteMs): array
    {
        $size = strlen($html);
        $meta = preg_match_all('/<meta\b[^>]*>/i', $html);
        $og = preg_match_all('/<meta\s+property=["\']og:/i', $html);
        $ld = preg_match_all('/<script\s+type=["\']application\/ld\+json["\']/i', $html);
        $imgs = preg_match_all('/<img\b[^>]*>/i', $html);
        $blocking = preg_match_all('/<script\b(?![^>]*defer)(?![^>]*type=["\']application\/ld\+json["\'])[^>]*>/i', $html);

        $score = $this->calculateScore($blocking, $ld, $og, $size, $imgs);

        return [
            'path' => $path,
            'score' => $score,
            'html_size' => $size,
            'meta_count' => $meta,
            'og_count' => $og,
            'ldjson_count' => $ld,
            'img_count' => $imgs,
            'blocking_scripts' => $blocking,
            'first_byte_ms' => $firstByteMs,
            'meta' => [
                'first_byte_ms' => $firstByteMs,
                'html_size' => $size,
                'meta_count' => $meta,
                'og_count' => $og,
                'ldjson_count' => $ld,
                'img_count' => $imgs,
                'blocking_scripts' => $blocking,
                'has_json_ld' => $ld > 0,
                'has_open_graph' => $og > 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function queuePersistence(array $payload): void
    {
        StoreSsrMetric::dispatch($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function persistMetric(array $payload): void
    {
        if ($this->storeInDatabase($payload)) {
            return;
        }

        $this->storeInJsonl($payload);
    }

    /**
     * @return array{score: int, path_count: int}
     */
    public function latestScoreSummary(): array
    {
        if (Schema::hasTable('ssr_metrics')) {
            $row = DB::table('ssr_metrics')->orderByDesc('id')->first();

            if ($row !== null) {
                return [
                    'score' => (int) $row->score,
                    'path_count' => 1,
                ];
            }
        }

        if (Storage::exists('metrics/last.json')) {
            $json = json_decode(Storage::get('metrics/last.json'), true) ?: [];
            $paths = count($json);
            $score = 0;

            foreach ($json as $row) {
                $score += (int) ($row['score'] ?? 0);
            }

            if ($paths > 0) {
                $score = (int) round($score / $paths);
            }

            return [
                'score' => $score,
                'path_count' => $paths,
            ];
        }

        return [
            'score' => 0,
            'path_count' => 0,
        ];
    }

    /**
     * @return array<int, array{date: string, average: float}>
     */
    public function dailyAverageScores(int $limit = 30): array
    {
        if (Schema::hasTable('ssr_metrics')) {
            return DB::table('ssr_metrics')
                ->selectRaw('date(created_at) as d, avg(score) as average')
                ->groupBy('d')
                ->orderBy('d')
                ->limit($limit)
                ->get()
                ->map(static fn ($row): array => [
                    'date' => (string) $row->d,
                    'average' => round((float) $row->average, 2),
                ])
                ->all();
        }

        if (Storage::exists('metrics/last.json')) {
            $json = json_decode(Storage::get('metrics/last.json'), true) ?: [];
            $sum = 0;
            $count = 0;

            foreach ($json as $row) {
                $sum += (int) ($row['score'] ?? 0);
                $count++;
            }

            $average = $count > 0 ? round($sum / $count, 2) : 0.0;

            return [[
                'date' => now()->toDateString(),
                'average' => $average,
            ]];
        }

        return [];
    }

    public function dropDatasetQuery(): ?Builder
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
                    }, 'aggregate')
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
     * @return array<int, array{path: string, avg_score: float, avg_block: float, ld: float, og: float}>
     */
    public function aggregateIssues(int $days = 2): array
    {
        if (Schema::hasTable('ssr_metrics')) {
            return DB::table('ssr_metrics')
                ->selectRaw('path, avg(score) as avg_score, avg(blocking_scripts) as avg_block, avg(ldjson_count) as ld, avg(og_count) as og')
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('path')
                ->get()
                ->map(static fn ($row): array => [
                    'path' => (string) $row->path,
                    'avg_score' => (float) $row->avg_score,
                    'avg_block' => (float) $row->avg_block,
                    'ld' => (float) $row->ld,
                    'og' => (float) $row->og,
                ])
                ->all();
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

            $decoded = json_decode($line, true);

            if (! is_array($decoded)) {
                continue;
            }

            $path = (string) ($decoded['path'] ?? '');

            if ($path === '') {
                continue;
            }

            $aggregated[$path] = $aggregated[$path] ?? [
                'sum_score' => 0.0,
                'count' => 0,
                'sum_block' => 0.0,
                'sum_ld' => 0.0,
                'sum_og' => 0.0,
            ];

            $aggregated[$path]['sum_score'] += (int) ($decoded['score'] ?? 0);
            $aggregated[$path]['count']++;
            $aggregated[$path]['sum_block'] += (int) ($decoded['blocking'] ?? 0);
            $aggregated[$path]['sum_ld'] += (int) ($decoded['ld'] ?? 0);
            $aggregated[$path]['sum_og'] += (int) ($decoded['og'] ?? 0);
        }

        return collect($aggregated)
            ->map(static function (array $row, string $path): array {
                $count = max(1, (int) $row['count']);

                return [
                    'path' => $path,
                    'avg_score' => $row['sum_score'] / $count,
                    'avg_block' => $row['sum_block'] / $count,
                    'ld' => $row['sum_ld'] / $count,
                    'og' => $row['sum_og'] / $count,
                ];
            })
            ->values()
            ->all();
    }

    private function calculateScore(int $blocking, int $ld, int $og, int $size, int $images): int
    {
        $weights = $this->config->get('ssrmetrics.weights', []);

        $score = 100;

        $blockingPenalty = min(
            $blocking * (int) ($weights['blocking_scripts']['penalty'] ?? 5),
            (int) ($weights['blocking_scripts']['max_penalty'] ?? 30)
        );

        $score -= $blockingPenalty;

        if ($ld === 0) {
            $score -= (int) ($weights['missing_ldjson'] ?? 10);
        }

        $minimumOg = (int) ($weights['minimum_open_graph_tags'] ?? 3);

        if ($og < $minimumOg) {
            $score -= (int) ($weights['missing_open_graph'] ?? 10);
        }

        if ($size > (int) ($weights['large_html_threshold'] ?? (900 * 1024))) {
            $score -= (int) ($weights['large_html_penalty'] ?? 20);
        }

        if ($images > (int) ($weights['image_threshold'] ?? 60)) {
            $score -= (int) ($weights['image_penalty'] ?? 10);
        }

        return max(0, $score);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeInDatabase(array $payload): bool
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return false;
        }

        try {
            $data = [
                'path' => $payload['path'],
                'score' => $payload['score'],
                'created_at' => now(),
            ];

            if (Schema::hasColumn('ssr_metrics', 'size') && isset($payload['html_size'])) {
                $data['size'] = $payload['html_size'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta_count') && isset($payload['meta_count'])) {
                $data['meta_count'] = $payload['meta_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'og_count') && isset($payload['og_count'])) {
                $data['og_count'] = $payload['og_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'ldjson_count') && isset($payload['ldjson_count'])) {
                $data['ldjson_count'] = $payload['ldjson_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'img_count') && isset($payload['img_count'])) {
                $data['img_count'] = $payload['img_count'];
            }

            if (Schema::hasColumn('ssr_metrics', 'blocking_scripts') && isset($payload['blocking_scripts'])) {
                $data['blocking_scripts'] = $payload['blocking_scripts'];
            }

            if (Schema::hasColumn('ssr_metrics', 'first_byte_ms') && isset($payload['first_byte_ms'])) {
                $data['first_byte_ms'] = $payload['first_byte_ms'];
            }

            if (Schema::hasColumn('ssr_metrics', 'meta') && isset($payload['meta'])) {
                $data['meta'] = json_encode($payload['meta'], JSON_THROW_ON_ERROR);
            }

            DB::table('ssr_metrics')->insert($data);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed storing SSR metric in database, falling back to JSONL.', [
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeInJsonl(array $payload): void
    {
        try {
            if (! Storage::exists('metrics')) {
                Storage::makeDirectory('metrics');
            }

            $record = [
                'ts' => now()->toIso8601String(),
                'path' => $payload['path'],
                'score' => $payload['score'],
                'size' => $payload['html_size'] ?? null,
                'html_size' => $payload['html_size'] ?? null,
                'meta' => $payload['meta'] ?? null,
                'meta_count' => $payload['meta_count'] ?? null,
                'og' => $payload['og_count'] ?? null,
                'ld' => $payload['ldjson_count'] ?? null,
                'imgs' => $payload['img_count'] ?? null,
                'blocking' => $payload['blocking_scripts'] ?? null,
                'first_byte_ms' => $payload['first_byte_ms'] ?? null,
                'has_json_ld' => ($payload['ldjson_count'] ?? 0) > 0,
                'has_open_graph' => ($payload['og_count'] ?? 0) > 0,
            ];

            Storage::append('metrics/ssr.jsonl', json_encode($record, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            Log::error('Failed storing SSR metric.', [
                'exception' => $e,
                'payload' => $payload,
            ]);
        }
    }
}
