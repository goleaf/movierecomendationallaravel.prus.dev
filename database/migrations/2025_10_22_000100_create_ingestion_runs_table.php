<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ingestion_runs')) {
            Schema::create('ingestion_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('source');
                $table->string('external_id');
                $table->date('date_key');
                $table->json('request_headers')->nullable();
                $table->json('request_payload')->nullable();
                $table->json('response_headers')->nullable();
                $table->json('response_payload')->nullable();
                $table->string('last_etag')->nullable();
                $table->timestamp('last_modified_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->unique(['source', 'external_id', 'date_key']);
            });
        }

        if (! Schema::hasTable('movies')) {
            return;
        }

        $now = CarbonImmutable::now();

        DB::table('movies')
            ->orderBy('id')
            ->select([
                'id',
                'imdb_tt',
                'title',
                'type',
                'year',
                'release_date',
                'imdb_rating',
                'imdb_votes',
                'runtime_min',
                'genres',
                'poster_url',
                'backdrop_url',
                'raw',
                'created_at',
                'updated_at',
            ])
            ->chunkById(100, function ($movies) use ($now): void {
                $records = [];

                foreach ($movies as $movie) {
                    $raw = $movie->raw !== null ? json_decode($movie->raw, true) : [];

                    if (! is_array($raw)) {
                        $raw = [];
                    }

                    $genres = $movie->genres !== null ? json_decode($movie->genres, true) : null;
                    $createdAt = $movie->created_at !== null
                        ? CarbonImmutable::parse($movie->created_at)
                        : $now;
                    $updatedAt = $movie->updated_at !== null
                        ? CarbonImmutable::parse($movie->updated_at)
                        : $createdAt;

                    $records[] = [
                        'source' => Arr::get($raw, 'source', 'seed'),
                        'external_id' => $movie->imdb_tt,
                        'date_key' => $createdAt->toDateString(),
                        'request_headers' => null,
                        'request_payload' => null,
                        'response_headers' => json_encode([
                            'x-ingestion-origin' => 'movies_table_backfill',
                        ], JSON_THROW_ON_ERROR),
                        'response_payload' => json_encode([
                            'movie' => [
                                'imdb_tt' => $movie->imdb_tt,
                                'title' => $movie->title,
                                'type' => $movie->type,
                                'year' => $movie->year,
                                'release_date' => $movie->release_date,
                                'imdb_rating' => $movie->imdb_rating,
                                'imdb_votes' => $movie->imdb_votes,
                                'runtime_min' => $movie->runtime_min,
                                'genres' => $genres,
                                'poster_url' => $movie->poster_url,
                                'backdrop_url' => $movie->backdrop_url,
                            ],
                            'raw' => $raw,
                        ], JSON_THROW_ON_ERROR),
                        'last_etag' => null,
                        'last_modified_at' => $updatedAt,
                        'started_at' => $createdAt,
                        'finished_at' => $updatedAt,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ];
                }

                if ($records === []) {
                    return;
                }

                DB::table('ingestion_runs')->upsert(
                    $records,
                    ['source', 'external_id', 'date_key']
                );
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestion_runs');
    }
};
