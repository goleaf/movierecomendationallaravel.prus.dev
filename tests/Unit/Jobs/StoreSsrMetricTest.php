<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\StoreSsrMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ssr-metrics')]
class StoreSsrMetricTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Carbon::setTestNow('2024-03-20 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_persists_payload_into_normalized_columns(): void
    {
        $payload = [
            'path' => '/sample',
            'score' => 94,
            'html_size' => 512_000,
            'meta_count' => 24,
            'og_count' => 3,
            'ldjson_count' => 2,
            'img_count' => 18,
            'blocking_scripts' => 1,
            'first_byte_ms' => 210,
            'recorded_at' => '2024-03-19T15:30:00Z',
        ];

        $job = new StoreSsrMetric($payload);
        $job->handle();

        $row = (array) DB::table('ssr_metrics')->first();

        $this->assertSame('/sample', $row['path']);
        $this->assertSame(94, (int) $row['score']);
        $this->assertSame(512000, (int) $row['html_size']);
        $this->assertSame(24, (int) $row['meta_count']);
        $this->assertSame(3, (int) $row['og_count']);
        $this->assertSame(2, (int) $row['ldjson_count']);
        $this->assertSame(18, (int) $row['img_count']);
        $this->assertSame(1, (int) $row['blocking_scripts']);
        $this->assertSame(210, (int) $row['first_byte_ms']);
        $this->assertTrue((bool) $row['has_json_ld']);
        $this->assertTrue((bool) $row['has_open_graph']);
        $this->assertSame('2024-03-19 15:30:00', $row['recorded_at']);
        $this->assertSame('2024-03-19 15:30:00', $row['created_at']);
        $this->assertSame('2024-03-19 15:30:00', $row['updated_at']);
    }
}
