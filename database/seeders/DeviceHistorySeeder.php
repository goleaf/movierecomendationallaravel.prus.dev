<?php

namespace Database\Seeders;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeviceHistorySeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('device_history') || ! Schema::hasTable('movies')) {
            return;
        }

        DB::table('device_history')->delete();

        $movies = Movie::query()->pluck('id')->all();
        if (empty($movies)) {
            return;
        }

        $faker = fake();
        $rows = [];
        $placements = ['home', 'show', 'trends'];

        foreach (range(0, 6) as $daysAgo) {
            $viewsPerDay = $faker->numberBetween(40, 90);
            $day = CarbonImmutable::now()->subDays($daysAgo);
            for ($i = 0; $i < $viewsPerDay; $i++) {
                $timestamp = $day
                    ->setHour($faker->numberBetween(8, 23))
                    ->setMinute($faker->numberBetween(0, 59))
                    ->setSecond($faker->numberBetween(0, 59));

                $rows[] = [
                    'device_id' => $faker->uuid(),
                    'movie_id' => $faker->randomElement($movies),
                    'placement' => $faker->randomElement($placements),
                    'viewed_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            DB::table('device_history')->insert($chunk);
        }
    }
}
