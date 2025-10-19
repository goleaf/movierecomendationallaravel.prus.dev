<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Analytics\SsrAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SsrAnalyticsServiceTest extends TestCase
{
    public function test_it_uses_jsonl_fallback_when_table_missing(): void
    {
        Storage::fake('local');

        $now = CarbonImmutable::create(2024, 5, 20, 12);
        Carbon::setTestNow($now);

        $records = [
            [
                'ts' => $now->subDay()->setTime(9, 0)->toIso8601String(),
                'path' => '/dropped',
                'score' => 90,
            ],
            [
                'ts' => $now->setTime(10, 0)->toIso8601String(),
                'path' => '/dropped',
                'score' => 50,
            ],
            [
                'ts' => $now->subDay()->setTime(11, 0)->toIso8601String(),
                'path' => '/steady',
                'score' => 70,
            ],
            [
                'ts' => $now->setTime(12, 0)->toIso8601String(),
                'path' => '/steady',
                'score' => 80,
            ],
        ];

        $payload = collect($records)
            ->map(fn (array $record): string => json_encode($record, JSON_THROW_ON_ERROR))
            ->implode("\n");

        Storage::disk('local')->put('metrics/ssr.jsonl', $payload);

        Schema::shouldReceive('hasTable')->with('ssr_metrics')->andReturnFalse();

        $service = new SsrAnalyticsService;

        $headline = $service->headline();
        $trend = $service->trend();
        $drops = $service->dropRows();

        $this->assertSame(73, $headline['score']);
        $this->assertSame(4, $headline['paths']);

        $this->assertSame([
            $now->subDay()->toDateString(),
            $now->toDateString(),
        ], $trend['labels']);

        $this->assertSame([
            80.0,
            65.0,
        ], $trend['datasets'][0]['data']);

        $this->assertCount(1, $drops);
        $this->assertSame('/dropped', $drops[0]['path']);
        $this->assertSame(50.0, $drops[0]['score_today']);
        $this->assertSame(90.0, $drops[0]['score_yesterday']);
        $this->assertSame(-40.0, $drops[0]['delta']);

        Carbon::setTestNow();
    }
}
