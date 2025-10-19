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

        if (Schema::hasColumn('ssr_metrics', 'ttfb_ms')) {
            return;
        }

        Schema::table('ssr_metrics', function (Blueprint $table): void {
            $table->unsignedInteger('ttfb_ms')->nullable()->after('score');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ssr_metrics')) {
            return;
        }

        if (! Schema::hasColumn('ssr_metrics', 'ttfb_ms')) {
            return;
        }

        Schema::table('ssr_metrics', function (Blueprint $table): void {
            $table->dropColumn('ttfb_ms');
        });
    }
};
