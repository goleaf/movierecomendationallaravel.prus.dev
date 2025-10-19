<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return;
        }

        Schema::table('ssr_metrics', function (Blueprint $table): void {
            if (! Schema::hasColumn('ssr_metrics', 'html_bytes')) {
                $table->unsignedInteger('html_bytes')->default(0)->after('size');
            }

            if (! Schema::hasColumn('ssr_metrics', 'has_json_ld')) {
                $table->boolean('has_json_ld')->default(false)->after('ldjson_count');
            }

            if (! Schema::hasColumn('ssr_metrics', 'has_open_graph')) {
                $table->boolean('has_open_graph')->default(false)->after('has_json_ld');
            }

            if (! Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $table->timestamp('collected_at')->nullable()->after('first_byte_ms');
            }
        });

        Schema::table('ssr_metrics', function (Blueprint $table): void {
            if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
                if (! $this->indexExists('ssr_metrics', 'ssr_metrics_collected_at_index')) {
                    $table->index('collected_at');
                }

                if (! $this->indexExists('ssr_metrics', 'ssr_metrics_path_collected_at_index')) {
                    $table->index(['path', 'collected_at']);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return;
        }

        Schema::table('ssr_metrics', function (Blueprint $table): void {
            if ($this->indexExists('ssr_metrics', 'ssr_metrics_path_collected_at_index')) {
                $table->dropIndex('ssr_metrics_path_collected_at_index');
            }

            if ($this->indexExists('ssr_metrics', 'ssr_metrics_collected_at_index')) {
                $table->dropIndex('ssr_metrics_collected_at_index');
            }
        });

        Schema::table('ssr_metrics', function (Blueprint $table): void {
            if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $table->dropColumn('collected_at');
            }

            if (Schema::hasColumn('ssr_metrics', 'has_open_graph')) {
                $table->dropColumn('has_open_graph');
            }

            if (Schema::hasColumn('ssr_metrics', 'has_json_ld')) {
                $table->dropColumn('has_json_ld');
            }

            if (Schema::hasColumn('ssr_metrics', 'html_bytes')) {
                $table->dropColumn('html_bytes');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $prefixedTable = $connection->getTablePrefix().$table;

        try {
            $indexes = $connection
                ->getDoctrineSchemaManager()
                ->introspectTable($prefixedTable)
                ->getIndexes();

            return array_key_exists($index, $indexes);
        } catch (\Throwable) {
            return false;
        }
    }
};
