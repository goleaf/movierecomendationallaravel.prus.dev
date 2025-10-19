<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ssr_metrics', function (Blueprint $table): void {
            if (! Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                $table->unsignedInteger('first_byte_ms')->default(0)->after('blocking_scripts');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ssr_metrics', function (Blueprint $table): void {
            if (Schema::hasColumn('ssr_metrics', 'first_byte_ms')) {
                $table->dropColumn('first_byte_ms');
            }
        });
    }
};
