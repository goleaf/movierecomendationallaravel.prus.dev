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
            if (! Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $table->timestamp('collected_at')->nullable()->after('score');
            }

            if (! Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                $table->unsignedInteger('first_byte_ms')->nullable()->after('collected_at');
            }

            if (! Schema::hasColumn('ssr_metrics', 'html_bytes')) {
                $table->unsignedInteger('html_bytes')->nullable()->after('first_byte_ms');
            }

            if (! Schema::hasColumn('ssr_metrics', 'meta_count')) {
                $table->unsignedInteger('meta_count')->default(0)->after('html_bytes');
            }

            if (! Schema::hasColumn('ssr_metrics', 'og_count')) {
                $table->unsignedInteger('og_count')->default(0)->after('meta_count');
            }

            if (! Schema::hasColumn('ssr_metrics', 'ldjson_count')) {
                $table->unsignedInteger('ldjson_count')->default(0)->after('og_count');
            }

            if (! Schema::hasColumn('ssr_metrics', 'img_count')) {
                $table->unsignedInteger('img_count')->default(0)->after('ldjson_count');
            }

            if (! Schema::hasColumn('ssr_metrics', 'blocking_scripts')) {
                $table->unsignedInteger('blocking_scripts')->default(0)->after('img_count');
            }

            if (! Schema::hasColumn('ssr_metrics', 'has_json_ld')) {
                $table->boolean('has_json_ld')->default(false)->after('blocking_scripts');
            }

            if (! Schema::hasColumn('ssr_metrics', 'has_open_graph')) {
                $table->boolean('has_open_graph')->default(false)->after('has_json_ld');
            }
        });

        if (Schema::hasColumn('ssr_metrics', 'html_bytes') && Schema::hasColumn('ssr_metrics', 'size')) {
            DB::statement('update ssr_metrics set html_bytes = size where html_bytes is null and size is not null');
        }

        if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
            DB::statement('update ssr_metrics set collected_at = created_at where collected_at is null and created_at is not null');
        }

        if (Schema::hasColumn('ssr_metrics', 'has_json_ld') && Schema::hasColumn('ssr_metrics', 'ldjson_count')) {
            DB::statement('update ssr_metrics set has_json_ld = ldjson_count > 0 where has_json_ld = 0');
        }

        if (Schema::hasColumn('ssr_metrics', 'has_open_graph') && Schema::hasColumn('ssr_metrics', 'og_count')) {
            DB::statement('update ssr_metrics set has_open_graph = og_count > 0 where has_open_graph = 0');
        }

        if ($this->missingIndex('ssr_metrics', 'ssr_metrics_path_collected_at_index')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->index(['path', 'collected_at'], 'ssr_metrics_path_collected_at_index');
            });
        }

        if ($this->missingIndex('ssr_metrics', 'ssr_metrics_collected_at_index')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->index('collected_at', 'ssr_metrics_collected_at_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return;
        }

        if (! $this->missingIndex('ssr_metrics', 'ssr_metrics_path_collected_at_index')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->dropIndex('ssr_metrics_path_collected_at_index');
            });
        }

        if (! $this->missingIndex('ssr_metrics', 'ssr_metrics_collected_at_index')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                $table->dropIndex('ssr_metrics_collected_at_index');
            });
        }

        $columns = collect([
            'collected_at',
            'first_byte_ms',
            'html_bytes',
            'has_json_ld',
            'has_open_graph',
        ])->filter(fn (string $column): bool => Schema::hasColumn('ssr_metrics', $column))->values()->all();

        if ($columns !== []) {
            Schema::table('ssr_metrics', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    private function missingIndex(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'select count(*) as aggregate from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?',
            [$database, $table, $index],
        );

        return (int) ($result[0]->aggregate ?? 0) === 0;
    }
};
