<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ssr_metrics')) {
            Schema::create('ssr_metrics', function (Blueprint $table): void {
                $table->id();
                $table->string('path');
                $table->unsignedTinyInteger('score');
                $table->unsignedBigInteger('html_size')->nullable();
                $table->unsignedInteger('meta_count')->default(0);
                $table->unsignedInteger('og_count')->default(0);
                $table->unsignedInteger('ldjson_count')->default(0);
                $table->unsignedInteger('img_count')->default(0);
                $table->unsignedInteger('blocking_scripts')->default(0);
                $table->unsignedInteger('first_byte_ms')->nullable();
                $table->boolean('has_json_ld')->default(false);
                $table->boolean('has_open_graph')->default(false);
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();

                $table->index(['path', 'recorded_at']);
                $table->index(['recorded_at', 'score']);
                $table->index('score');
            });

            return;
        }

        Schema::create('ssr_metrics_new', function (Blueprint $table): void {
            $table->id();
            $table->string('path');
            $table->unsignedTinyInteger('score');
            $table->unsignedBigInteger('html_size')->nullable();
            $table->unsignedInteger('meta_count')->default(0);
            $table->unsignedInteger('og_count')->default(0);
            $table->unsignedInteger('ldjson_count')->default(0);
            $table->unsignedInteger('img_count')->default(0);
            $table->unsignedInteger('blocking_scripts')->default(0);
            $table->unsignedInteger('first_byte_ms')->nullable();
            $table->boolean('has_json_ld')->default(false);
            $table->boolean('has_open_graph')->default(false);
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['path', 'recorded_at']);
            $table->index(['recorded_at', 'score']);
            $table->index('score');
        });

        $hasSize = Schema::hasColumn('ssr_metrics', 'size');
        $hasMetaCount = Schema::hasColumn('ssr_metrics', 'meta_count');
        $hasOgCount = Schema::hasColumn('ssr_metrics', 'og_count');
        $hasLdCount = Schema::hasColumn('ssr_metrics', 'ldjson_count');
        $hasImgCount = Schema::hasColumn('ssr_metrics', 'img_count');
        $hasBlocking = Schema::hasColumn('ssr_metrics', 'blocking_scripts');
        $hasFirstByte = Schema::hasColumn('ssr_metrics', 'first_byte_ms');
        $hasMetaJson = Schema::hasColumn('ssr_metrics', 'meta');
        $hasRecordedAt = Schema::hasColumn('ssr_metrics', 'recorded_at');

        DB::table('ssr_metrics')->orderBy('id')->chunkById(500, function ($rows) use (
            $hasBlocking,
            $hasFirstByte,
            $hasImgCount,
            $hasLdCount,
            $hasMetaCount,
            $hasMetaJson,
            $hasOgCount,
            $hasRecordedAt,
            $hasSize
        ): void {
            foreach ($rows as $row) {
                $metaPayload = [];

                if ($hasMetaJson && $row->meta !== null) {
                    try {
                        $decoded = json_decode((string) $row->meta, true, 512, JSON_THROW_ON_ERROR);
                        if (is_array($decoded)) {
                            $metaPayload = $decoded;
                        }
                    } catch (\Throwable) {
                        $metaPayload = [];
                    }
                }

                $htmlSize = $metaPayload['html_size'] ?? null;
                if ($hasSize && isset($row->size)) {
                    $htmlSize = (int) $row->size;
                } elseif (isset($row->html_size)) {
                    $htmlSize = (int) $row->html_size;
                }

                $metaCount = $metaPayload['meta_count'] ?? null;
                if ($hasMetaCount && isset($row->meta_count)) {
                    $metaCount = (int) $row->meta_count;
                }

                $ogCount = $metaPayload['og_count'] ?? null;
                if ($hasOgCount && isset($row->og_count)) {
                    $ogCount = (int) $row->og_count;
                }

                $ldCount = $metaPayload['ldjson_count'] ?? null;
                if ($hasLdCount && isset($row->ldjson_count)) {
                    $ldCount = (int) $row->ldjson_count;
                }

                $imgCount = $metaPayload['img_count'] ?? null;
                if ($hasImgCount && isset($row->img_count)) {
                    $imgCount = (int) $row->img_count;
                }

                $blocking = $metaPayload['blocking_scripts'] ?? null;
                if ($hasBlocking && isset($row->blocking_scripts)) {
                    $blocking = (int) $row->blocking_scripts;
                }

                $firstByte = $metaPayload['first_byte_ms'] ?? null;
                if ($hasFirstByte && isset($row->first_byte_ms)) {
                    $firstByte = (int) $row->first_byte_ms;
                }

                $recordedAt = $hasRecordedAt && isset($row->recorded_at)
                    ? Carbon::parse($row->recorded_at)
                    : (isset($row->created_at) ? Carbon::parse($row->created_at) : now());

                DB::table('ssr_metrics_new')->insert([
                    'path' => $row->path,
                    'score' => (int) $row->score,
                    'html_size' => $htmlSize === null ? null : (int) $htmlSize,
                    'meta_count' => $metaCount === null ? 0 : (int) $metaCount,
                    'og_count' => $ogCount === null ? 0 : (int) $ogCount,
                    'ldjson_count' => $ldCount === null ? 0 : (int) $ldCount,
                    'img_count' => $imgCount === null ? 0 : (int) $imgCount,
                    'blocking_scripts' => $blocking === null ? 0 : (int) $blocking,
                    'first_byte_ms' => $firstByte === null ? null : (int) $firstByte,
                    'has_json_ld' => $metaPayload['has_json_ld'] ?? (($ldCount ?? 0) > 0),
                    'has_open_graph' => $metaPayload['has_open_graph'] ?? (($ogCount ?? 0) > 0),
                    'recorded_at' => $recordedAt,
                    'created_at' => isset($row->created_at) ? Carbon::parse($row->created_at) : $recordedAt,
                    'updated_at' => isset($row->updated_at) ? Carbon::parse($row->updated_at) : $recordedAt,
                ]);
            }
        });

        Schema::drop('ssr_metrics');
        Schema::rename('ssr_metrics_new', 'ssr_metrics');
    }

    public function down(): void
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return;
        }

        Schema::create('ssr_metrics_legacy', function (Blueprint $table): void {
            $table->id();
            $table->string('path');
            $table->unsignedTinyInteger('score');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        DB::table('ssr_metrics')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $meta = [
                    'html_size' => $row->html_size,
                    'meta_count' => $row->meta_count,
                    'og_count' => $row->og_count,
                    'ldjson_count' => $row->ldjson_count,
                    'img_count' => $row->img_count,
                    'blocking_scripts' => $row->blocking_scripts,
                    'first_byte_ms' => $row->first_byte_ms,
                    'has_json_ld' => (bool) $row->has_json_ld,
                    'has_open_graph' => (bool) $row->has_open_graph,
                ];

                DB::table('ssr_metrics_legacy')->insert([
                    'path' => $row->path,
                    'score' => $row->score,
                    'meta' => json_encode($meta, JSON_THROW_ON_ERROR),
                    'created_at' => $row->recorded_at ?: $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        });

        Schema::drop('ssr_metrics');
        Schema::rename('ssr_metrics_legacy', 'ssr_metrics');
    }
};
