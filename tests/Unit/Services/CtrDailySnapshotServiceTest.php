<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\CtrDailySnapshot;
use App\Services\Analytics\CtrDailySnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CtrDailySnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_daily_snapshots_with_computed_rates(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-01-12 09:00:00'));

        $movieId = DB::table('movies')->insertGetId([
            'imdb_tt' => 'tt1000001',
            'title' => 'Snapshot Test',
            'type' => 'movie',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $day = CarbonImmutable::parse('2025-01-11 00:00:00');
        $impressionTime = $day->setTime(10, 30);

        $impressions = [];
        foreach (range(1, 10) as $index) {
            $impressions[] = [
                'device_id' => 'device-a-'.$index,
                'placement' => 'home',
                'variant' => 'A',
                'movie_id' => $movieId,
                'created_at' => $impressionTime,
                'updated_at' => $impressionTime,
            ];
        }

        foreach (range(1, 5) as $index) {
            $impressions[] = [
                'device_id' => 'device-b-'.$index,
                'placement' => 'home',
                'variant' => 'B',
                'movie_id' => $movieId,
                'created_at' => $impressionTime,
                'updated_at' => $impressionTime,
            ];
        }

        DB::table('rec_ab_logs')->insert($impressions);

        $clicks = [];
        foreach (range(1, 2) as $index) {
            $clicks[] = [
                'movie_id' => $movieId,
                'placement' => 'home',
                'variant' => 'A',
                'created_at' => $impressionTime,
                'updated_at' => $impressionTime,
            ];
        }

        $clicks[] = [
            'movie_id' => $movieId,
            'placement' => 'home',
            'variant' => 'B',
            'created_at' => $impressionTime,
            'updated_at' => $impressionTime,
        ];

        DB::table('rec_clicks')->insert($clicks);

        $service = app(CtrDailySnapshotService::class);
        $service->aggregateForDate($day);

        $snapshotA = CtrDailySnapshot::query()
            ->whereDate('snapshot_date', $day->toDateString())
            ->where('variant', 'A')
            ->first();
        $snapshotB = CtrDailySnapshot::query()
            ->whereDate('snapshot_date', $day->toDateString())
            ->where('variant', 'B')
            ->first();

        $this->assertNotNull($snapshotA);
        $this->assertNotNull($snapshotB);

        $this->assertSame(10, $snapshotA->impressions);
        $this->assertSame(2, $snapshotA->clicks);
        $this->assertSame(0, $snapshotA->views);
        $this->assertEqualsWithDelta(20.0, $snapshotA->ctr, 0.0001);
        $this->assertEqualsWithDelta(0.0, $snapshotA->view_rate, 0.0001);

        $this->assertSame(5, $snapshotB->impressions);
        $this->assertSame(1, $snapshotB->clicks);
        $this->assertSame(0, $snapshotB->views);
        $this->assertEqualsWithDelta(20.0, $snapshotB->ctr, 0.0001);
        $this->assertEqualsWithDelta(0.0, $snapshotB->view_rate, 0.0001);

        // Running the aggregation again updates the existing snapshot instead of duplicating it.
        DB::table('rec_clicks')->insert([
            'movie_id' => $movieId,
            'placement' => 'home',
            'variant' => 'A',
            'created_at' => $impressionTime,
            'updated_at' => $impressionTime,
        ]);

        $service->aggregateForDate($day);

        $updatedSnapshotA = CtrDailySnapshot::query()
            ->whereDate('snapshot_date', $day->toDateString())
            ->where('variant', 'A')
            ->first();

        $this->assertNotNull($updatedSnapshotA);
        $this->assertSame(3, $updatedSnapshotA->clicks);
        $this->assertEqualsWithDelta(30.0, $updatedSnapshotA->ctr, 0.0001);
        $this->assertEqualsWithDelta(0.0, $updatedSnapshotA->view_rate, 0.0001);

        CarbonImmutable::setTestNow();
    }
}
