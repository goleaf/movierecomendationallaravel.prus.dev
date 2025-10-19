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
            if (! Schema::hasColumn('ssr_metrics', 'html_bytes')) {
                if (Schema::hasColumn('ssr_metrics', 'size')) {
                    $table->unsignedInteger('html_bytes')->nullable()->after('size');
                } elseif (Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                    $table->unsignedInteger('html_bytes')->nullable()->after('first_byte_ms');
                } else {
                    $table->unsignedInteger('html_bytes')->nullable();
                }
            }

            if (! Schema::hasColumn('ssr_metrics', 'has_json_ld')) {
                if (Schema::hasColumn('ssr_metrics', 'ldjson_count')) {
                    $table->boolean('has_json_ld')->default(false)->after('ldjson_count');
                } else {
                    $table->boolean('has_json_ld')->default(false);
                }
            }

            if (! Schema::hasColumn('ssr_metrics', 'has_open_graph')) {
                if (Schema::hasColumn('ssr_metrics', 'og_count')) {
                    $table->boolean('has_open_graph')->default(false)->after('has_json_ld');
                } else {
                    $table->boolean('has_open_graph')->default(false);
                }
            }

            if (! Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $table->timestamp('collected_at')->nullable()->after('updated_at');
            }
        });

        if (Schema::hasColumn('ssr_metrics', 'html_bytes')) {
            if (Schema::hasColumn('ssr_metrics', 'size')) {
                DB::table('ssr_metrics')->whereNull('html_bytes')->update([
                    'html_bytes' => DB::raw('size'),
                ]);
            }
        }

        if (Schema::hasColumn('ssr_metrics', 'has_json_ld') && Schema::hasColumn('ssr_metrics', 'ldjson_count')) {
            DB::table('ssr_metrics')->whereNull('has_json_ld')->update([
                'has_json_ld' => DB::raw('ldjson_count > 0'),
            ]);
        }

        if (Schema::hasColumn('ssr_metrics', 'has_open_graph') && Schema::hasColumn('ssr_metrics', 'og_count')) {
            DB::table('ssr_metrics')->whereNull('has_open_graph')->update([
                'has_open_graph' => DB::raw('og_count > 0'),
            ]);
        }

        if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
            DB::table('ssr_metrics')->whereNull('collected_at')->update([
                'collected_at' => DB::raw('coalesce(updated_at, created_at)'),
            ]);
        }

        Schema::table('ssr_metrics', function (Blueprint $table): void {
            if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
                $table->index('collected_at', 'ssr_metrics_collected_at_index');
                if (Schema::hasColumn('ssr_metrics', 'path')) {
                    $table->index(['path', 'collected_at'], 'ssr_metrics_path_collected_at_index');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return;
        }

        if (Schema::hasColumn('ssr_metrics', 'collected_at')) {
            Schema::table('ssr_metrics', function (Blueprint $table): void {
                try {
                    $table->dropIndex('ssr_metrics_collected_at_index');
                } catch (Throwable) {
                    // Index already removed.
                }

                if (Schema::hasColumn('ssr_metrics', 'path')) {
                    try {
                        $table->dropIndex('ssr_metrics_path_collected_at_index');
                    } catch (Throwable) {
                        // Index already removed.
                    }
                }
            });
        }

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
};
