<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SsrMetricsService
{
    /** @var array<int, array<int, array{date: string, score: float}>> */
    private array $trendCache = [];

    /** @var array{today: float, yesterday: float, delta: float, rolling: float, tracked_paths: int, today_paths: int}|null */
    private ?array $summaryCache = null;

    public function trend(int $days = 30): Collection
    {
        if (array_key_exists($days, $this->trendCache)) {
            return collect($this->trendCache[$days]);
        }

        $result = [];

        if (Schema::hasTable('ssr_metrics')) {
            $startDate = Carbon::now()->subDays(max($days - 1, 0))->toDateString();

            $rows = DB::table('ssr_metrics')
                ->selectRaw('date(created_at) as d, avg(score) as avg_score')
                ->whereDate('created_at', '>=', $startDate)
                ->groupBy('d')
                ->orderBy('d')
                ->limit($days)
                ->get();

            $result = $rows->map(static fn ($row): array => [
                'date' => (string) $row->d,
                'score' => round((float) $row->avg_score, 2),
            ])->all();
        } elseif (Storage::exists('metrics/last.json')) {
            try {
                $payload = json_decode(Storage::get('metrics/last.json'), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $payload = [];
            }

            if (is_array($payload) && $payload !== []) {
                $average = collect($payload)
                    ->pluck('score')
                    ->filter(static fn ($value) => is_numeric($value))
                    ->map(static fn ($value) => (float) $value)
                    ->avg();

                $result = [[
                    'date' => Carbon::now()->toDateString(),
                    'score' => round((float) $average, 2),
                ]];
            }
        }

        $this->trendCache[$days] = $result;

        return collect($result);
    }

    public function summary(): array
    {
        if ($this->summaryCache !== null) {
            return $this->summaryCache;
        }

        $today = Carbon::now()->toDateString();
        $yesterday = Carbon::now()->subDay()->toDateString();
        $rollingStart = Carbon::now()->subDays(6)->startOfDay();
        $summary = [
            'today' => 0.0,
            'yesterday' => 0.0,
            'delta' => 0.0,
            'rolling' => 0.0,
            'tracked_paths' => 0,
            'today_paths' => 0,
        ];

        if (Schema::hasTable('ssr_metrics')) {
            $baseQuery = DB::table('ssr_metrics');

            $todayAverage = (clone $baseQuery)->whereDate('created_at', $today)->avg('score');
            $yesterdayAverage = (clone $baseQuery)->whereDate('created_at', $yesterday)->avg('score');
            $rollingAverage = (clone $baseQuery)
                ->where('created_at', '>=', $rollingStart)
                ->avg('score');
            $todayPaths = (clone $baseQuery)
                ->whereDate('created_at', $today)
                ->distinct('path')
                ->count('path');
            $trackedPaths = (clone $baseQuery)
                ->where('created_at', '>=', $rollingStart)
                ->distinct('path')
                ->count('path');

            $summary = [
                'today' => round((float) $todayAverage, 2),
                'yesterday' => round((float) $yesterdayAverage, 2),
                'delta' => round((float) $todayAverage - (float) $yesterdayAverage, 2),
                'rolling' => round((float) $rollingAverage, 2),
                'tracked_paths' => (int) $trackedPaths,
                'today_paths' => (int) $todayPaths,
            ];
        } elseif (Storage::exists('metrics/last.json')) {
            try {
                $payload = json_decode(Storage::get('metrics/last.json'), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $payload = [];
            }

            if (is_array($payload) && $payload !== []) {
                $scores = collect($payload)
                    ->pluck('score')
                    ->filter(static fn ($value) => is_numeric($value))
                    ->map(static fn ($value) => (float) $value);

                $average = round((float) $scores->avg(), 2);
                $count = $scores->count();

                $summary = [
                    'today' => $average,
                    'yesterday' => 0.0,
                    'delta' => $average,
                    'rolling' => $average,
                    'tracked_paths' => $count,
                    'today_paths' => $count,
                ];
            }
        }

        return $this->summaryCache = $summary;
    }
}
