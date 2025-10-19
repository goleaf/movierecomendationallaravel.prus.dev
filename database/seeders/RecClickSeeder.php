<?php

namespace Database\Seeders;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecClickSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('rec_clicks') || ! Schema::hasTable('movies')) {
            return;
        }

        DB::table('rec_clicks')->delete();

        $movies = Movie::query()
            ->orderByDesc('imdb_votes')
            ->limit(8)
            ->get();

        if ($movies->isEmpty()) {
            return;
        }

        $faker = fake();
        $placements = ['home', 'show', 'trends'];
        $variants = ['A', 'B'];
        $rows = [];

        foreach ($movies as $index => $movie) {
            foreach ($variants as $variant) {
                $clicks = $faker->numberBetween(10 + (7 - $index) * 2, 40 + (7 - $index) * 3);
                for ($i = 0; $i < $clicks; $i++) {
                    $day = CarbonImmutable::now()->subDays($faker->numberBetween(0, 6));
                    $timestamp = $day
                        ->setHour($faker->numberBetween(9, 23))
                        ->setMinute($faker->numberBetween(0, 59))
                        ->setSecond($faker->numberBetween(0, 59));

                    $rows[] = [
                        'movie_id' => $movie->id,
                        'device_id' => $faker->uuid(),
                        'placement' => $faker->randomElement($placements),
                        'variant' => $variant,
                        'source' => $faker->randomElement(['hero', 'module', 'email', null]),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }
        }

        $chunks = array_chunk($rows, 500);
        foreach ($chunks as $chunk) {
            DB::table('rec_clicks')->insert($chunk);
        }
    }
}
