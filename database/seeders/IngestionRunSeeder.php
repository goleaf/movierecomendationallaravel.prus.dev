<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\IngestionRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class IngestionRunSeeder extends Seeder
{
    public function run(): void
    {
        $dateKey = CarbonImmutable::now()->subDay()->startOfDay();

        $startedAt = $dateKey->addHours(2);
        $finishedAt = $startedAt->addMinutes(1);

        IngestionRun::query()->updateOrCreate(
            [
                'source' => 'tmdb',
                'external_id' => 'discover-movie-seed',
                'date_key' => $dateKey->toDateString(),
            ],
            [
                'request_headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer demo-token',
                ],
                'request_payload' => [
                    'endpoint' => '/3/discover/movie',
                    'query' => [
                        'language' => 'en-US',
                        'page' => 1,
                        'sort_by' => 'popularity.desc',
                    ],
                ],
                'response_headers' => [
                    'x-request-id' => 'tmdb-demo-run',
                    'x-cache-status' => 'HIT',
                ],
                'response_payload' => [
                    'status' => 'ok',
                    'imported' => 25,
                    'notes' => 'Seed ingestion run for local demos.',
                ],
                'last_etag' => '"demo-tmdb-etag"',
                'last_modified_at' => $finishedAt,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
            ]
        );
    }
}
