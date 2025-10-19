<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ingestion_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source');
            $table->string('external_id');
            $table->date('date_key');
            $table->string('last_etag')->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id', 'date_key']);
        });

        $this->backfillFromExistingMetadata();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestion_runs');
    }

    private function backfillFromExistingMetadata(): void
    {
        if (! Schema::hasTable('movies')) {
            return;
        }

        $movies = DB::table('movies')
            ->select(['id', 'imdb_tt', 'created_at', 'raw'])
            ->whereNotNull('raw')
            ->get();

        $now = now();

        foreach ($movies as $movie) {
            $raw = $this->decodeRawPayload($movie->raw);

            $source = $this->normalizeNullable($raw['source'] ?? null);
            $externalId = $this->normalizeNullable($raw['external_id'] ?? $movie->imdb_tt);
            $ingestedAt = $this->parseDate($raw['ingested_at'] ?? null)
                ?? $this->parseDate($movie->created_at ?? null);

            if ($source === null || $source === '') {
                continue;
            }

            if ($externalId === null || $externalId === '') {
                continue;
            }

            $dateKey = $ingestedAt?->toDateString() ?? $now->toDateString();

            DB::table('ingestion_runs')->updateOrInsert(
                [
                    'source' => $source,
                    'external_id' => $externalId,
                    'date_key' => $dateKey,
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'meta' => array_filter([
                        'movie_id' => $movie->id,
                        'raw' => $raw,
                    ]),
                ],
            );
        }
    }

    /**
     * @param  string|resource|null  $raw
     * @return array<string, mixed>
     */
    private function decodeRawPayload($raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (is_resource($raw)) {
            $raw = stream_get_contents($raw) ?: '';
        }

        if ($raw === '' || $raw === null) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || strtolower($trimmed) === 'null') {
            return null;
        }

        return $trimmed;
    }

    private function parseDate(?string $value): ?Carbon
    {
        $normalized = $this->normalizeNullable($value);

        if ($normalized === null) {
            return null;
        }

        try {
            return Carbon::parse($normalized);
        } catch (\Throwable) {
            return null;
        }
    }
};
