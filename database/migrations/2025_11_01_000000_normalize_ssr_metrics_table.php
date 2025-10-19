<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return;
        }

        Schema::table('ssr_metrics', function (Blueprint $table): void {
            if (! Schema::hasColumn('ssr_metrics', 'recorded_at')) {
                $table->timestamp('recorded_at')->nullable()->after('score');
            }

            if (! Schema::hasColumn('ssr_metrics', 'payload')) {
                $table->json('payload')->nullable()->after('recorded_at');
            }
        });

        $this->backfillPayload();
        $this->refreshIndexes();
        $this->dropLegacyColumns();
    }

    public function down(): void
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return;
        }

        Schema::table('ssr_metrics', function (Blueprint $table): void {
            if (! Schema::hasColumn('ssr_metrics', 'size')) {
                $table->unsignedInteger('size')->nullable()->after('score');
            }

            if (! Schema::hasColumn('ssr_metrics', 'meta_count')) {
                $table->unsignedInteger('meta_count')->nullable()->after('size');
            }

            if (! Schema::hasColumn('ssr_metrics', 'og_count')) {
                $table->unsignedInteger('og_count')->nullable()->after('meta_count');
            }

            if (! Schema::hasColumn('ssr_metrics', 'ldjson_count')) {
                $table->unsignedInteger('ldjson_count')->nullable()->after('og_count');
            }

            if (! Schema::hasColumn('ssr_metrics', 'img_count')) {
                $table->unsignedInteger('img_count')->nullable()->after('ldjson_count');
            }

            if (! Schema::hasColumn('ssr_metrics', 'blocking_scripts')) {
                $table->unsignedInteger('blocking_scripts')->nullable()->after('img_count');
            }

            if (! Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                $table->unsignedInteger('first_byte_ms')->default(0)->after('blocking_scripts');
            }

            if (! Schema::hasColumn('ssr_metrics', 'meta')) {
                $table->json('meta')->nullable()->after('first_byte_ms');
            }
        });

        $this->restoreLegacyData();
        $this->dropNewIndexes();
        $this->dropModernColumns();
        $this->restoreLegacyIndexes();
    }

    private function backfillPayload(): void
    {
        $hasSize = Schema::hasColumn('ssr_metrics', 'size');
        $hasMetaCount = Schema::hasColumn('ssr_metrics', 'meta_count');
        $hasOgCount = Schema::hasColumn('ssr_metrics', 'og_count');
        $hasLdjsonCount = Schema::hasColumn('ssr_metrics', 'ldjson_count');
        $hasImgCount = Schema::hasColumn('ssr_metrics', 'img_count');
        $hasBlocking = Schema::hasColumn('ssr_metrics', 'blocking_scripts');
        $hasFirstByte = Schema::hasColumn('ssr_metrics', 'first_byte_ms');
        $hasMeta = Schema::hasColumn('ssr_metrics', 'meta');
        $hasRecordedAt = Schema::hasColumn('ssr_metrics', 'recorded_at');

        DB::table('ssr_metrics')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($row) use (
                $hasSize,
                $hasMetaCount,
                $hasOgCount,
                $hasLdjsonCount,
                $hasImgCount,
                $hasBlocking,
                $hasFirstByte,
                $hasMeta,
                $hasRecordedAt
            ): void {
                $payload = [];

                if ($hasSize && isset($row->size)) {
                    $payload['html_size'] = (int) $row->size;
                }

                $counts = [];
                if ($hasMetaCount && isset($row->meta_count)) {
                    $counts['meta'] = (int) $row->meta_count;
                }
                if ($hasOgCount && isset($row->og_count)) {
                    $counts['og'] = (int) $row->og_count;
                }
                if ($hasLdjsonCount && isset($row->ldjson_count)) {
                    $counts['ldjson'] = (int) $row->ldjson_count;
                }
                if ($hasImgCount && isset($row->img_count)) {
                    $counts['img'] = (int) $row->img_count;
                }
                if ($hasBlocking && isset($row->blocking_scripts)) {
                    $counts['blocking_scripts'] = (int) $row->blocking_scripts;
                }
                if ($counts !== []) {
                    $payload['counts'] = $counts;
                }

                if ($hasFirstByte && isset($row->first_byte_ms)) {
                    $payload['first_byte_ms'] = (int) $row->first_byte_ms;
                }

                if ($hasMeta && isset($row->meta)) {
                    $decoded = null;
                    if ($row->meta !== null) {
                        $decoded = json_decode((string) $row->meta, true);
                    }

                    if (is_array($decoded)) {
                        $payload['meta'] = $decoded;
                    }
                }

                $updates = [];
                if ($payload !== []) {
                    $updates['payload'] = json_encode($payload, JSON_THROW_ON_ERROR);
                }

                if ($hasRecordedAt && ! isset($row->recorded_at) && isset($row->created_at)) {
                    $updates['recorded_at'] = $row->created_at;
                }

                if ($updates !== []) {
                    DB::table('ssr_metrics')->where('id', $row->id)->update($updates);
                }
            });
    }

    private function refreshIndexes(): void
    {
        if ($this->indexExists('ssr_metrics', 'ssr_metrics_path_created_at_index')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->dropIndex('ssr_metrics_path_created_at_index');
            });
        }

        if (! $this->indexExists('ssr_metrics', 'ssr_metrics_recorded_at_index')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->index('recorded_at');
            });
        }

        if (! $this->indexExists('ssr_metrics', 'ssr_metrics_path_recorded_at_index')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->index(['path', 'recorded_at']);
            });
        }
    }

    private function dropLegacyColumns(): void
    {
        $columns = array_filter([
            Schema::hasColumn('ssr_metrics', 'size') ? 'size' : null,
            Schema::hasColumn('ssr_metrics', 'meta_count') ? 'meta_count' : null,
            Schema::hasColumn('ssr_metrics', 'og_count') ? 'og_count' : null,
            Schema::hasColumn('ssr_metrics', 'ldjson_count') ? 'ldjson_count' : null,
            Schema::hasColumn('ssr_metrics', 'img_count') ? 'img_count' : null,
            Schema::hasColumn('ssr_metrics', 'blocking_scripts') ? 'blocking_scripts' : null,
            Schema::hasColumn('ssr_metrics', 'first_byte_ms') ? 'first_byte_ms' : null,
            Schema::hasColumn('ssr_metrics', 'meta') ? 'meta' : null,
        ]);

        if ($columns === []) {
            return;
        }

        Schema::table('ssr_metrics', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    private function restoreLegacyData(): void
    {
        $hasPayload = Schema::hasColumn('ssr_metrics', 'payload');

        if (! $hasPayload) {
            return;
        }

        DB::table('ssr_metrics')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($row): void {
                $payload = [];
                if ($row->payload !== null) {
                    $decoded = json_decode((string) $row->payload, true);
                    if (is_array($decoded)) {
                        $payload = $decoded;
                    }
                }

                $counts = is_array($payload['counts'] ?? null) ? $payload['counts'] : [];

                $updates = [
                    'size' => isset($payload['html_size']) ? (int) $payload['html_size'] : null,
                    'meta_count' => isset($counts['meta']) ? (int) $counts['meta'] : null,
                    'og_count' => isset($counts['og']) ? (int) $counts['og'] : null,
                    'ldjson_count' => isset($counts['ldjson']) ? (int) $counts['ldjson'] : null,
                    'img_count' => isset($counts['img']) ? (int) $counts['img'] : null,
                    'blocking_scripts' => isset($counts['blocking_scripts']) ? (int) $counts['blocking_scripts'] : null,
                    'first_byte_ms' => isset($payload['first_byte_ms']) ? (int) $payload['first_byte_ms'] : 0,
                    'meta' => isset($payload['meta']) ? json_encode($payload['meta'], JSON_THROW_ON_ERROR) : null,
                ];

                DB::table('ssr_metrics')->where('id', $row->id)->update($updates);
            });
    }

    private function dropNewIndexes(): void
    {
        if ($this->indexExists('ssr_metrics', 'ssr_metrics_path_recorded_at_index')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->dropIndex('ssr_metrics_path_recorded_at_index');
            });
        }

        if ($this->indexExists('ssr_metrics', 'ssr_metrics_recorded_at_index')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->dropIndex('ssr_metrics_recorded_at_index');
            });
        }
    }

    private function dropModernColumns(): void
    {
        $columns = array_filter([
            Schema::hasColumn('ssr_metrics', 'payload') ? 'payload' : null,
            Schema::hasColumn('ssr_metrics', 'recorded_at') ? 'recorded_at' : null,
        ]);

        if ($columns === []) {
            return;
        }

        Schema::table('ssr_metrics', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    private function restoreLegacyIndexes(): void
    {
        if (! $this->indexExists('ssr_metrics', 'ssr_metrics_path_created_at_index')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->index(['path', 'created_at']);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = DB::connection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $index],
        );

        return $result !== [];
    }
};
