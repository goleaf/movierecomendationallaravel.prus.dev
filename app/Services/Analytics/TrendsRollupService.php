<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;

class TrendsRollupService
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function increment(int $movieId, CarbonInterface $timestamp): void
    {
        if (! Schema::hasTable('rec_trending_rollups')) {
            return;
        }

        $capturedOn = $timestamp->toDateString();
        $now = now();

        $updated = $this->db->table('rec_trending_rollups')
            ->where('movie_id', $movieId)
            ->where('captured_on', $capturedOn)
            ->increment('clicks', 1, ['updated_at' => $now]);

        if ($updated === 0) {
            $this->db->table('rec_trending_rollups')->insert([
                'movie_id' => $movieId,
                'captured_on' => $capturedOn,
                'clicks' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function ensureBackfill(CarbonImmutable $from, CarbonImmutable $to): void
    {
        if (! Schema::hasTable('rec_trending_rollups') || ! Schema::hasTable('rec_clicks')) {
            return;
        }

        $start = $from->startOfDay();
        $end = $to->endOfDay();

        $datesWithClicks = $this->db->table('rec_clicks')
            ->selectRaw('DATE(created_at) as captured_on')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('captured_on')
            ->pluck('captured_on')
            ->map(static fn ($date) => (string) $date)
            ->unique();

        if ($datesWithClicks->isEmpty()) {
            return;
        }

        $existingDates = $this->db->table('rec_trending_rollups')
            ->select('captured_on')
            ->whereBetween('captured_on', [$start->toDateString(), $end->toDateString()])
            ->distinct()
            ->pluck('captured_on')
            ->map(static fn ($date) => (string) $date)
            ->unique();

        $missingDates = $datesWithClicks->diff($existingDates);
        if ($missingDates->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($missingDates as $date) {
            $day = CarbonImmutable::createFromFormat('Y-m-d', $date);
            $dayStart = $day->startOfDay();
            $dayEnd = $day->endOfDay();

            $rows = $this->db->table('rec_clicks')
                ->selectRaw('movie_id, count(*) as clicks')
                ->whereBetween('created_at', [$dayStart->toDateTimeString(), $dayEnd->toDateTimeString()])
                ->groupBy('movie_id')
                ->get()
                ->map(static function ($row) use ($date, $now): array {
                    return [
                        'movie_id' => (int) $row->movie_id,
                        'captured_on' => $date,
                        'clicks' => (int) $row->clicks,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })
                ->all();

            if ($rows !== []) {
                $this->db->table('rec_trending_rollups')->insert($rows);
            }
        }
    }
}
