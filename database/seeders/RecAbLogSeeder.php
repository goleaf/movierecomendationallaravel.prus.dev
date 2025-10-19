<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecAbLogSeeder extends Seeder
{
    public function run(): void
    {
        $movieIds = Movie::query()->pluck('id');
        if ($movieIds->isEmpty()) {
            return;
        }

        $devices = collect(range(1, 12))->map(fn (int $i) => sprintf('device-%02d', $i));
        $placements = ['home', 'show', 'trends'];
        $variants = ['A', 'B'];
        $base = now();

        $records = [];
        foreach ($devices as $deviceId) {
            foreach ($placements as $placement) {
                foreach ($variants as $variant) {
                    foreach (range(0, 6) as $dayOffset) {
                        $count = random_int(4, 9);
                        for ($i = 0; $i < $count; $i++) {
                            $moment = $base
                                ->subDays($dayOffset)
                                ->setTime(random_int(9, 23), random_int(0, 59), random_int(0, 59));

                            $records[] = [
                                'device_id' => $deviceId,
                                'movie_id' => $movieIds->random(),
                                'placement' => $placement,
                                'variant' => $variant,
                                'created_at' => $moment,
                                'updated_at' => $moment,
                            ];
                        }
                    }
                }
            }
        }

        foreach (array_chunk($records, 1000) as $chunk) {
            DB::table('rec_ab_logs')->insert($chunk);
        }
    }
}
