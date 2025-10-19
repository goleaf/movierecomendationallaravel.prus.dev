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
            if (! Schema::hasColumn('ssr_metrics', 'size')) {
                $table->unsignedInteger('size')->default(0)->after('score');
            }

            if (! Schema::hasColumn('ssr_metrics', 'meta_count')) {
                $table->unsignedInteger('meta_count')->default(0);
            }

            if (! Schema::hasColumn('ssr_metrics', 'og_count')) {
                $table->unsignedInteger('og_count')->default(0);
            }

            if (! Schema::hasColumn('ssr_metrics', 'ldjson_count')) {
                $table->unsignedInteger('ldjson_count')->default(0);
            }

            if (! Schema::hasColumn('ssr_metrics', 'img_count')) {
                $table->unsignedInteger('img_count')->default(0);
            }

            if (! Schema::hasColumn('ssr_metrics', 'blocking_scripts')) {
                $table->unsignedInteger('blocking_scripts')->default(0);
            }

            if (! Schema::hasColumn('ssr_metrics', 'insights')) {
                $table->json('insights')->nullable();
            }
        });

        $this->addIndex('ssr_metrics', ['path', 'created_at'], 'ssr_metrics_path_created_at_index');
        $this->addIndex('ssr_metrics', ['created_at'], 'ssr_metrics_created_at_index');
        $this->addIndex('ssr_metrics', ['score'], 'ssr_metrics_score_index');
    }

    public function down(): void
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return;
        }

        $this->dropIndex('ssr_metrics', 'ssr_metrics_path_created_at_index');
        $this->dropIndex('ssr_metrics', 'ssr_metrics_created_at_index');
        $this->dropIndex('ssr_metrics', 'ssr_metrics_score_index');

        $columns = array_filter([
            Schema::hasColumn('ssr_metrics', 'insights') ? 'insights' : null,
            Schema::hasColumn('ssr_metrics', 'blocking_scripts') ? 'blocking_scripts' : null,
            Schema::hasColumn('ssr_metrics', 'img_count') ? 'img_count' : null,
            Schema::hasColumn('ssr_metrics', 'ldjson_count') ? 'ldjson_count' : null,
            Schema::hasColumn('ssr_metrics', 'og_count') ? 'og_count' : null,
            Schema::hasColumn('ssr_metrics', 'meta_count') ? 'meta_count' : null,
            Schema::hasColumn('ssr_metrics', 'size') ? 'size' : null,
        ]);

        if ($columns !== []) {
            Schema::table('ssr_metrics', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $name): void {
            $table->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! $this->indexExists($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($name): void {
            $table->dropIndex($name);
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'sqlite' => $this->sqliteIndexExists($table, $name),
            'pgsql' => $this->postgresIndexExists($table, $name),
            'mysql', 'mariadb' => $this->mysqlIndexExists($table, $name),
            default => false,
        };
    }

    private function sqliteIndexExists(string $table, string $name): bool
    {
        $results = Schema::getConnection()->select(sprintf("PRAGMA index_list('%s')", $table));

        foreach ($results as $row) {
            $indexName = is_array($row) ? ($row['name'] ?? null) : ($row->name ?? null);

            if ($indexName === $name) {
                return true;
            }
        }

        return false;
    }

    private function postgresIndexExists(string $table, string $name): bool
    {
        $results = Schema::getConnection()->select(
            'select indexname from pg_indexes where tablename = ?',
            [strtolower($table)]
        );

        foreach ($results as $row) {
            $indexName = is_array($row) ? ($row['indexname'] ?? null) : ($row->indexname ?? null);

            if ($indexName === $name) {
                return true;
            }
        }

        return false;
    }

    private function mysqlIndexExists(string $table, string $name): bool
    {
        $results = Schema::getConnection()->select(
            sprintf('show index from `%s` where Key_name = ?', $table),
            [$name]
        );

        return count($results) > 0;
    }
};
