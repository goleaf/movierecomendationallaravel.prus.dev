<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecClickSeeder extends Seeder
{
    public function run(): void
    {
        $logs = DB::table('rec_ab_logs')
            ->orderBy('created_at')
            ->get();

        if ($logs->isEmpty()) {
            return;
        }

        $records = [];
        foreach ($logs as $log) {
            if (random_int(1, 100) > 32) {
                continue;
            }

            $clickedAt = Carbon::parse($log->created_at)
                ->addMinutes(random_int(1, 90));

            $records[] = [
                'device_id' => $log->device_id,
                'movie_id' => $log->movie_id,
                'placement' => $log->placement,
                'variant' => $log->variant,
                'position' => random_int(1, 12),
                'created_at' => $clickedAt,
                'updated_at' => $clickedAt,
            ];
        }

        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('rec_clicks')->insert($chunk);
        }
    }
}
