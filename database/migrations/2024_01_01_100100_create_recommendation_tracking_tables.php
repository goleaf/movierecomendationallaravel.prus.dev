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
        if (! Schema::hasTable('rec_ab_logs')) {
            Schema::create('rec_ab_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
                $table->string('device_id');
                $table->string('placement', 32);
                $table->string('variant', 1);
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['variant', 'created_at']);
                $table->index(['placement', 'created_at']);
            });
        }

        if (! Schema::hasTable('rec_clicks')) {
            Schema::create('rec_clicks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
                $table->string('device_id');
                $table->string('placement', 32);
                $table->string('variant', 1);
                $table->string('source')->nullable();
                $table->timestamps();
                $table->index(['variant', 'created_at']);
                $table->index(['placement', 'created_at']);
            });
        }

        if (! Schema::hasTable('device_history')) {
            Schema::create('device_history', function (Blueprint $table): void {
                $table->id();
                $table->string('device_id');
                $table->foreignId('movie_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('placement', 32)->nullable();
                $table->timestamp('viewed_at');
                $table->timestamps();
                $table->index('viewed_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_history');
        Schema::dropIfExists('rec_clicks');
        Schema::dropIfExists('rec_ab_logs');
    }
};
