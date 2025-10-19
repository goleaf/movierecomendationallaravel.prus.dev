<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeviceHistorySeeder extends Seeder
{
    public function run(): void
    {
        $clicks = DB::table('rec_clicks')
            ->orderBy('created_at')
            ->get();

        if ($clicks->isEmpty()) {
            return;
        }

        $records = [];
        foreach ($clicks as $click) {
            if (random_int(1, 100) > 58) {
                continue;
            }

            $viewedAt = Carbon::parse($click->created_at)
                ->addMinutes(random_int(5, 180));

            $records[] = [
                'device_id' => $click->device_id,
                'movie_id' => $click->movie_id,
                'placement' => $click->placement,
                'viewed_at' => $viewedAt,
                'created_at' => $viewedAt,
                'updated_at' => $viewedAt,
            ];
        }

        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('device_history')->insert($chunk);
        }
    }
}
