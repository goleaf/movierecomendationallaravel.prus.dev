<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ctr_daily_snapshots')) {
            return;
        }

        Schema::create('ctr_daily_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date');
            $table->string('variant', 8);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('view_rate', 8, 4)->default(0);
            $table->timestamps();

            $table->unique(['snapshot_date', 'variant']);
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctr_daily_snapshots');
    }
};
