<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SsrMetricsAggregator
{
    /**
     * @return array{
     *     summary: array{score: float, first_byte_ms: float, samples: int, paths: int},
     *     daily: array<int, array{
     *         date: string,
     *         score: float,
     *         first_byte_ms: float,
     *         samples: int,
     *         paths: int,
     *         rolling_score: float,
     *         rolling_first_byte_ms: float,
     *     }>,
     * }
     */
    public function aggregate(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $records = $this->loadRecords($from, $to);

        $rangeStart = $from->startOfDay();
        $rangeEnd = $to->startOfDay();

        if ($records !== []) {
            $firstTimestamp = $records[0]['timestamp'];
            foreach ($records as $record) {
                if ($record['timestamp']->lt($firstTimestamp)) {
                    $firstTimestamp = $record['timestamp'];
                }
            }

            if ($firstTimestamp->startOfDay()->gt($rangeStart)) {
                $rangeStart = $firstTimestamp->startOfDay();
            }
        }

        $summary = [
            'score' => 0.0,
            'first_byte_ms' => 0.0,
            'samples' => 0,
            'paths' => 0,
        ];

        $uniquePaths = [];
        $scoreSum = 0.0;
        $firstByteSum = 0.0;
        $firstByteSamples = 0;

        $dailyStats = [];

        foreach ($records as $record) {
            $summary['samples']++;
            $scoreSum += $record['score'];
            $uniquePaths[$record['path']] = true;

            if ($record['first_byte_ms'] !== null) {
                $firstByteSum += $record['first_byte_ms'];
                $firstByteSamples++;
            }

            $date = $record['timestamp']->toDateString();

            if (! array_key_exists($date, $dailyStats)) {
                $dailyStats[$date] = [
                    'score_sum' => 0.0,
                    'samples' => 0,
                    'first_byte_sum' => 0.0,
                    'first_byte_samples' => 0,
                    'paths' => [],
                ];
            }

            $dailyStats[$date]['score_sum'] += $record['score'];
            $dailyStats[$date]['samples']++;

            if ($record['first_byte_ms'] !== null) {
                $dailyStats[$date]['first_byte_sum'] += $record['first_byte_ms'];
                $dailyStats[$date]['first_byte_samples']++;
            }

            $dailyStats[$date]['paths'][$record['path']] = true;
        }

        $summary['paths'] = count($uniquePaths);
        if ($summary['samples'] > 0) {
            $summary['score'] = round($scoreSum / $summary['samples'], 2);
        }

        if ($firstByteSamples > 0) {
            $summary['first_byte_ms'] = round($firstByteSum / $firstByteSamples, 2);
        }

        $daily = [];
        $scoreSums = [];
        $sampleCounts = [];
        $firstByteSums = [];
        $firstByteSampleCounts = [];

        for ($cursor = $rangeStart; $cursor->lte($rangeEnd); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();

            $stats = $dailyStats[$date] ?? [
                'score_sum' => 0.0,
                'samples' => 0,
                'first_byte_sum' => 0.0,
                'first_byte_samples' => 0,
                'paths' => [],
            ];

            $scoreSumForDay = $stats['score_sum'];
            $samplesForDay = $stats['samples'];
            $firstByteSumForDay = $stats['first_byte_sum'];
            $firstByteSamplesForDay = $stats['first_byte_samples'];
            $pathsForDay = count($stats['paths']);

            $daily[] = [
                'date' => $date,
                'score' => $samplesForDay > 0 ? round($scoreSumForDay / $samplesForDay, 2) : 0.0,
                'first_byte_ms' => $firstByteSamplesForDay > 0 ? round($firstByteSumForDay / $firstByteSamplesForDay, 2) : 0.0,
                'samples' => $samplesForDay,
                'paths' => $pathsForDay,
                'rolling_score' => 0.0,
                'rolling_first_byte_ms' => 0.0,
            ];

            $scoreSums[] = $scoreSumForDay;
            $sampleCounts[] = $samplesForDay;
            $firstByteSums[] = $firstByteSumForDay;
            $firstByteSampleCounts[] = $firstByteSamplesForDay;
        }

        $rollingScoreSum = 0.0;
        $rollingSamples = 0;
        $rollingFirstByteSum = 0.0;
        $rollingFirstByteSamples = 0;

        $windowScoreSums = [];
        $windowSamples = [];
        $windowFirstByteSums = [];
        $windowFirstByteSamples = [];

        foreach ($daily as $index => &$day) {
            $rollingScoreSum += $scoreSums[$index];
            $rollingSamples += $sampleCounts[$index];
            $rollingFirstByteSum += $firstByteSums[$index];
            $rollingFirstByteSamples += $firstByteSampleCounts[$index];

            $windowScoreSums[] = $scoreSums[$index];
            $windowSamples[] = $sampleCounts[$index];
            $windowFirstByteSums[] = $firstByteSums[$index];
            $windowFirstByteSamples[] = $firstByteSampleCounts[$index];

            if (count($windowScoreSums) > 7) {
                $rollingScoreSum -= array_shift($windowScoreSums);
                $rollingSamples -= array_shift($windowSamples);
                $rollingFirstByteSum -= array_shift($windowFirstByteSums);
                $rollingFirstByteSamples -= array_shift($windowFirstByteSamples);
            }

            $day['rolling_score'] = $rollingSamples > 0 ? round($rollingScoreSum / $rollingSamples, 2) : 0.0;
            $day['rolling_first_byte_ms'] = $rollingFirstByteSamples > 0 ? round($rollingFirstByteSum / $rollingFirstByteSamples, 2) : 0.0;
        }
        unset($day);

        return [
            'summary' => $summary,
            'daily' => $daily,
        ];
    }

    /**
     * @return array<int, array{timestamp: CarbonImmutable, path: string, score: float, first_byte_ms: float|null}>
     */
    private function loadRecords(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $records = $this->loadFromDatabase($from, $to);

        if ($records !== []) {
            return $records;
        }

        return $this->loadFromFallback($from, $to);
    }

    /**
     * @return array<int, array{timestamp: CarbonImmutable, path: string, score: float, first_byte_ms: float|null}>
     */
    private function loadFromDatabase(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return [];
        }

        $timestampColumn = $this->timestampColumn();

        $columns = [
            'path',
            'score',
            $timestampColumn.' as collected_at',
        ];

        $hasFirstByte = Schema::hasColumn('ssr_metrics', 'first_byte_ms');

        if ($hasFirstByte) {
            $columns[] = 'first_byte_ms';
        }

        $rows = DB::table('ssr_metrics')
            ->select($columns)
            ->whereNotNull($timestampColumn)
            ->whereBetween($timestampColumn, [
                $from->toDateTimeString(),
                $to->toDateTimeString(),
            ])
            ->orderBy($timestampColumn)
            ->orderBy('id')
            ->get();

        $records = [];

        foreach ($rows as $row) {
            $timestamp = $this->parseTimestamp($row->collected_at);

            if ($timestamp === null) {
                continue;
            }

            $score = $this->parseNumeric($row->score);
            if ($score === null) {
                continue;
            }

            $firstByte = $hasFirstByte ? $this->parseNumeric($row->first_byte_ms) : null;

            $records[] = [
                'timestamp' => $timestamp,
                'path' => $this->normalizePath($row->path ?? '/'),
                'score' => $score,
                'first_byte_ms' => $firstByte,
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array{timestamp: CarbonImmutable, path: string, score: float, first_byte_ms: float|null}>
     */
    private function loadFromFallback(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $records = $this->loadFromJsonl($from, $to);

        if ($records !== []) {
            return $records;
        }

        return $this->loadFromJson($from, $to);
    }

    /**
     * @return array<int, array{timestamp: CarbonImmutable, path: string, score: float, first_byte_ms: float|null}>
     */
    private function loadFromJsonl(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! Storage::exists('metrics/ssr.jsonl')) {
            return [];
        }

        $contents = trim((string) Storage::get('metrics/ssr.jsonl'));
        if ($contents === '') {
            return [];
        }

        $records = [];
        $lines = preg_split('/\r?\n/', $contents) ?: [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                continue;
            }

            $timestamp = $this->parseTimestamp($decoded['collected_at'] ?? $decoded['timestamp'] ?? $decoded['ts'] ?? null);
            if ($timestamp === null) {
                continue;
            }

            if ($timestamp->lt($from) || $timestamp->gt($to)) {
                continue;
            }

            $score = $this->parseNumeric($decoded['score'] ?? null);
            if ($score === null) {
                continue;
            }

            $firstByte = $this->parseNumeric($decoded['first_byte_ms'] ?? null);

            $records[] = [
                'timestamp' => $timestamp,
                'path' => $this->normalizePath((string) ($decoded['path'] ?? '/')),
                'score' => $score,
                'first_byte_ms' => $firstByte,
            ];
        }

        usort($records, static function (array $a, array $b): int {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        return $records;
    }

    /**
     * @return array<int, array{timestamp: CarbonImmutable, path: string, score: float, first_byte_ms: float|null}>
     */
    private function loadFromJson(CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! Storage::exists('metrics/last.json')) {
            return [];
        }

        $decoded = json_decode((string) Storage::get('metrics/last.json'), true);

        if (! is_array($decoded)) {
            return [];
        }

        $entries = array_is_list($decoded) ? $decoded : array_values($decoded);

        $records = [];
        $fallbackTimestamp = $to;

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $timestamp = $this->parseTimestamp($entry['collected_at'] ?? $entry['timestamp'] ?? $entry['ts'] ?? null) ?? $fallbackTimestamp;

            if ($timestamp->lt($from) || $timestamp->gt($to)) {
                continue;
            }

            $score = $this->parseNumeric($entry['score'] ?? null);
            if ($score === null) {
                continue;
            }

            $firstByte = $this->parseNumeric($entry['first_byte_ms'] ?? $entry['first_byte'] ?? null);

            $records[] = [
                'timestamp' => $timestamp,
                'path' => $this->normalizePath((string) ($entry['path'] ?? '/')),
                'score' => $score,
                'first_byte_ms' => $firstByte,
            ];
        }

        usort($records, static function (array $a, array $b): int {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        return $records;
    }

    private function normalizePath(string $path): string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            return '/';
        }

        return '/'.ltrim($normalized, '/');
    }

    private function parseTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestamp((int) $value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function parseNumeric(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function timestampColumn(): string
    {
        return Schema::hasColumn('ssr_metrics', 'collected_at') ? 'collected_at' : 'created_at';
    }
}
