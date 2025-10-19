<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\IngestionRun;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class IngestionRunSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today();

        IngestionRun::query()->updateOrCreate(
            [
                'source' => 'tmdb',
                'external_id' => 'tmdb:movie-translations',
                'date_key' => $today,
            ],
            [
                'last_etag' => 'seed-etag-tmdb-translations',
                'last_modified_at' => Carbon::now()->subMinutes(30),
                'meta' => [
                    'status' => 'completed',
                    'processed' => 48,
                    'notes' => 'Seeded TMDB translations run for local demos.',
                ],
            ],
        );
    }
}
