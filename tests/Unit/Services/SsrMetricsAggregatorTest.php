<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Analytics\SsrMetricsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SsrMetricsAggregatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_trend_returns_latest_dates_in_chronological_order(): void
    {
        Carbon::setTestNow('2025-02-10 12:00:00');
        $aggregator = app(SsrMetricsAggregator::class);

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
                'first_byte_ms' => 120 + $offset,
                'html_bytes' => 1_000,
                'has_json_ld' => true,
                'has_open_graph' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'collected_at' => $timestamp,
            ]);
        }

        $result = $aggregator->trend(30);
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

        Carbon::setTestNow();
    }

    public function test_summary_computes_period_deltas(): void
    {
        Carbon::setTestNow('2024-03-20 12:00:00');

        $now = Carbon::now();

        $samples = [
            ['path' => '/', 'score' => 88, 'first_byte_ms' => 244, 'days' => 0],
            ['path' => '/trends', 'score' => 92, 'first_byte_ms' => 176, 'days' => 0],
            ['path' => '/movies/1', 'score' => 94, 'first_byte_ms' => 192, 'days' => 0],
            ['path' => '/', 'score' => 96, 'first_byte_ms' => 185, 'days' => 1],
            ['path' => '/trends', 'score' => 90, 'first_byte_ms' => 201, 'days' => 1],
            ['path' => '/', 'score' => 94, 'first_byte_ms' => 198, 'days' => 2],
            ['path' => '/trends', 'score' => 91, 'first_byte_ms' => 207, 'days' => 2],
            ['path' => '/movies/1', 'score' => 95, 'first_byte_ms' => 200, 'days' => 2],
            ['path' => '/', 'score' => 90, 'first_byte_ms' => 215, 'days' => 8],
            ['path' => '/trends', 'score' => 88, 'first_byte_ms' => 210, 'days' => 8],
            ['path' => '/movies/1', 'score' => 89, 'first_byte_ms' => 208, 'days' => 8],
        ];

        foreach ($samples as $sample) {
            $timestamp = $now->copy()->subDays($sample['days']);

            DB::table('ssr_metrics')->insert([
                'path' => $sample['path'],
                'score' => $sample['score'],
                'size' => 1_000,
                'meta_count' => 5,
                'og_count' => 3,
                'ldjson_count' => 2,
                'img_count' => 3,
                'blocking_scripts' => 0,
                'first_byte_ms' => $sample['first_byte_ms'],
                'html_bytes' => 1_000,
                'has_json_ld' => true,
                'has_open_graph' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'collected_at' => $timestamp,
            ]);
        }

        $summary = app(SsrMetricsAggregator::class)->summary();
        $periods = $summary['periods'];

        $this->assertSame(3, $summary['paths']);
        $this->assertSame(8, $summary['samples']);

        $today = $periods['today'];
        $yesterday = $periods['yesterday'];
        $week = $periods['seven_days'];

        $this->assertEqualsWithDelta(91.33, $today['score_average'], 0.01);
        $this->assertEqualsWithDelta(-1.67, $today['score_delta'], 0.01);
        $this->assertEqualsWithDelta(204.0, $today['first_byte_average'], 0.01);
        $this->assertEqualsWithDelta(11.0, $today['first_byte_delta'], 0.01);

        $this->assertEqualsWithDelta(93.0, $yesterday['score_average'], 0.01);
        $this->assertEqualsWithDelta(-0.33, $yesterday['score_delta'], 0.01);
        $this->assertEqualsWithDelta(193.0, $yesterday['first_byte_average'], 0.01);
        $this->assertEqualsWithDelta(-8.67, $yesterday['first_byte_delta'], 0.01);

        $this->assertEqualsWithDelta(92.5, $week['score_average'], 0.01);
        $this->assertEqualsWithDelta(3.83, $week['score_delta'], 0.01);
        $this->assertEqualsWithDelta(200.38, $week['first_byte_average'], 0.01);
        $this->assertEqualsWithDelta(-10.63, $week['first_byte_delta'], 0.01);

        Carbon::setTestNow();
    }
}
