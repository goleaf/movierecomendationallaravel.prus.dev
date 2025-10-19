<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\CtrDailySnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AggregateCtrDailySnapshotsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_aggregates_yesterdays_data_by_default(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-02-01 08:00:00'));

        $movieId = DB::table('movies')->insertGetId([
            'imdb_tt' => 'tt2000001',
            'title' => 'Command Movie',
            'type' => 'movie',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yesterday = CarbonImmutable::yesterday();
        $timestamp = $yesterday->setTime(13, 45);

        $records = [];
        foreach (range(1, 4) as $index) {
            $records[] = [
                'device_id' => 'imp-a-'.$index,
                'placement' => 'home',
                'variant' => 'A',
                'movie_id' => $movieId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach (range(1, 6) as $index) {
            $records[] = [
                'device_id' => 'imp-b-'.$index,
                'placement' => 'home',
                'variant' => 'B',
                'movie_id' => $movieId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('rec_ab_logs')->insert($records);

        DB::table('rec_clicks')->insert([
            [
                'movie_id' => $movieId,
                'placement' => 'home',
                'variant' => 'A',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'movie_id' => $movieId,
                'placement' => 'home',
                'variant' => 'B',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);

        $this->artisan('analytics:aggregate-ctr-snapshots')
            ->expectsOutputToContain('Aggregated CTR snapshots')
            ->assertExitCode(0);

        $date = $yesterday->toDateString();

        $snapshots = CtrDailySnapshot::query()
            ->whereDate('snapshot_date', $date)
            ->orderBy('variant')
            ->get();

        $this->assertCount(2, $snapshots);
        $this->assertSame(['A', 'B'], $snapshots->pluck('variant')->all());

        $snapshotA = $snapshots->firstWhere('variant', 'A');
        $snapshotB = $snapshots->firstWhere('variant', 'B');

        $this->assertNotNull($snapshotA);
        $this->assertNotNull($snapshotB);
        $this->assertSame(4, $snapshotA->impressions);
        $this->assertSame(6, $snapshotB->impressions);
        $this->assertSame(1, $snapshotA->clicks);
        $this->assertSame(1, $snapshotB->clicks);

        CarbonImmutable::setTestNow();
    }
}
