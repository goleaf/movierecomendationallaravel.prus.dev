<?php

namespace Database\Seeders;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecAbLogSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('rec_ab_logs') || ! Schema::hasTable('movies')) {
            return;
        }

        DB::table('rec_ab_logs')->delete();

        $movies = Movie::query()->pluck('id')->all();
        if (empty($movies)) {
            return;
        }

        $faker = fake();
        $placements = ['home', 'show', 'trends'];
        $variants = ['A', 'B'];
        $rows = [];

        foreach (range(0, 6) as $daysAgo) {
            $day = CarbonImmutable::now()->subDays($daysAgo);
            foreach ($placements as $placement) {
                foreach ($variants as $variant) {
                    $impressions = $faker->numberBetween(35, 70);
                    for ($i = 0; $i < $impressions; $i++) {
                        $timestamp = $day
                            ->setHour($faker->numberBetween(8, 23))
                            ->setMinute($faker->numberBetween(0, 59))
                            ->setSecond($faker->numberBetween(0, 59));

                        $rows[] = [
                            'movie_id' => $faker->randomElement($movies),
                            'device_id' => $faker->uuid(),
                            'placement' => $placement,
                            'variant' => $variant,
                            'meta' => json_encode([
                                'session_id' => $faker->uuid(),
                            ]),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                }
            }
        }

        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            DB::table('rec_ab_logs')->insert($chunk);
        }
    }
}
