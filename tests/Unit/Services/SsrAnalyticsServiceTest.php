<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Analytics\SsrAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SsrAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_trend_returns_latest_dates_in_chronological_order(): void
    {
        $service = app(SsrAnalyticsService::class);

        $startDate = Carbon::parse('2025-01-01')->startOfDay();

        foreach (range(0, 34) as $offset) {
            $timestamp = $startDate->copy()->addDays($offset);

            DB::table('ssr_metrics')->insert([
                'path' => '/path-'.$offset,
                'score' => 50 + $offset,
                'size' => 1_000,
                'meta_count' => 5,
                'og_count' => 2,
                'ldjson_count' => 1,
                'img_count' => 3,
                'blocking_scripts' => 0,
                'first_byte_ms' => 120,
                'html_bytes' => 1_000,
                'has_json_ld' => true,
                'has_open_graph' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'collected_at' => $timestamp,
            ]);
        }

        $result = $service->trend(30);
        $labels = $result['labels'];
        $series = $result['datasets'][0]['data'];

        $this->assertCount(30, $labels);
        $this->assertCount(30, $series);

        $expectedDates = collect(range(5, 34))
            ->map(fn (int $offset): string => $startDate->copy()->addDays($offset)->toDateString())
            ->all();

        $expectedScores = collect(range(5, 34))
            ->map(fn (int $offset): float => (float) (50 + $offset))
            ->all();

        $this->assertSame($expectedDates, $labels);
        $this->assertSame($expectedScores, $series);

        $this->assertSame($expectedDates[0], $labels[0]);
        $this->assertSame(end($expectedDates), end($labels));
    }
}
