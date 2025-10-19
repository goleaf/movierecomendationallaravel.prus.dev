<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = $this->schema();

        if ($schema->hasTable('rec_ab_logs')) {
            $schema->table('rec_ab_logs', function (Blueprint $table): void {
                $table->index(['placement', 'variant', 'created_at'], 'rec_ab_logs_placement_variant_created_at_index');
                $table->index(['device_id', 'placement', 'created_at'], 'rec_ab_logs_device_placement_created_at_index');
            });

            if ($this->usingSqlite()) {
                DB::statement("CREATE INDEX IF NOT EXISTS rec_ab_logs_variant_a_created_at_index ON rec_ab_logs (created_at) WHERE variant = 'A'");
                DB::statement("CREATE INDEX IF NOT EXISTS rec_ab_logs_variant_b_created_at_index ON rec_ab_logs (created_at) WHERE variant = 'B'");
            }
        }

        if ($schema->hasTable('rec_clicks')) {
            $schema->table('rec_clicks', function (Blueprint $table): void {
                $table->index(['placement', 'variant', 'created_at'], 'rec_clicks_placement_variant_created_at_index');
                $table->index(['device_id', 'placement', 'created_at'], 'rec_clicks_device_placement_created_at_index');
                $table->index(['movie_id', 'created_at'], 'rec_clicks_movie_created_at_index');
            });

            if ($this->usingSqlite()) {
                DB::statement("CREATE INDEX IF NOT EXISTS rec_clicks_variant_a_created_at_index ON rec_clicks (created_at) WHERE variant = 'A'");
                DB::statement("CREATE INDEX IF NOT EXISTS rec_clicks_variant_b_created_at_index ON rec_clicks (created_at) WHERE variant = 'B'");
            }
        }

        $deviceHistoryColumn = $this->deviceHistoryPlacementColumn();
        if ($deviceHistoryColumn !== null) {
            $schema->table('device_history', function (Blueprint $table) use ($deviceHistoryColumn): void {
                $table->index([$deviceHistoryColumn, 'viewed_at'], 'device_history_'.$deviceHistoryColumn.'_viewed_at_index');
                $table->index(['device_id', $deviceHistoryColumn, 'viewed_at'], 'device_history_device_'.$deviceHistoryColumn.'_viewed_at_index');
            });

            if ($this->usingSqlite()) {
                DB::statement(
                    sprintf(
                        "CREATE INDEX IF NOT EXISTS device_history_%s_not_null_index ON device_history (%s) WHERE %s IS NOT NULL",
                        $deviceHistoryColumn,
                        $deviceHistoryColumn,
                        $deviceHistoryColumn,
                    )
                );
            }
        }
    }

    public function down(): void
    {
        $schema = $this->schema();

        if ($schema->hasTable('rec_ab_logs')) {
            $schema->table('rec_ab_logs', function (Blueprint $table): void {
                $table->dropIndex('rec_ab_logs_placement_variant_created_at_index');
                $table->dropIndex('rec_ab_logs_device_placement_created_at_index');
            });

            if ($this->usingSqlite()) {
                DB::statement('DROP INDEX IF EXISTS rec_ab_logs_variant_a_created_at_index');
                DB::statement('DROP INDEX IF EXISTS rec_ab_logs_variant_b_created_at_index');
            }
        }

        if ($schema->hasTable('rec_clicks')) {
            $schema->table('rec_clicks', function (Blueprint $table): void {
                $table->dropIndex('rec_clicks_placement_variant_created_at_index');
                $table->dropIndex('rec_clicks_device_placement_created_at_index');
                $table->dropIndex('rec_clicks_movie_created_at_index');
            });

            if ($this->usingSqlite()) {
                DB::statement('DROP INDEX IF EXISTS rec_clicks_variant_a_created_at_index');
                DB::statement('DROP INDEX IF EXISTS rec_clicks_variant_b_created_at_index');
            }
        }

        $deviceHistoryColumn = $this->deviceHistoryPlacementColumn();
        if ($deviceHistoryColumn !== null) {
            $schema->table('device_history', function (Blueprint $table) use ($deviceHistoryColumn): void {
                $table->dropIndex('device_history_'.$deviceHistoryColumn.'_viewed_at_index');
                $table->dropIndex('device_history_device_'.$deviceHistoryColumn.'_viewed_at_index');
            });

            if ($this->usingSqlite()) {
                DB::statement(sprintf('DROP INDEX IF EXISTS device_history_%s_not_null_index', $deviceHistoryColumn));
            }
        }
    }

    private function schema(): Builder
    {
        return Schema::connection($this->connection);
    }

    private function usingSqlite(): bool
    {
        return $this->schema()->getConnection()->getDriverName() === 'sqlite';
    }

    private function deviceHistoryPlacementColumn(): ?string
    {
        $schema = $this->schema();

        if (! $schema->hasTable('device_history')) {
            return null;
        }

        if ($schema->hasColumn('device_history', 'placement')) {
            return 'placement';
        }

        if ($schema->hasColumn('device_history', 'page')) {
            return 'page';
        }

        return null;
    }
};
