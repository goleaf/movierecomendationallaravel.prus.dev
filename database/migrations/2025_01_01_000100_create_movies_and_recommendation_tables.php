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
        if (! Schema::hasTable('movies')) {
            Schema::create('movies', function (Blueprint $table): void {
                $table->id();
                $table->string('imdb_tt')->unique();
                $table->string('title');
                $table->text('plot')->nullable();
                $table->string('type', 32)->default('movie');
                $table->unsignedSmallInteger('year')->nullable();
                $table->date('release_date')->nullable();
                $table->decimal('imdb_rating', 3, 1)->nullable();
                $table->unsignedInteger('imdb_votes')->nullable();
                $table->unsignedSmallInteger('runtime_min')->nullable();
                $table->json('genres')->nullable();
                $table->string('poster_url')->nullable();
                $table->string('backdrop_url')->nullable();
                $table->json('translations')->nullable();
                $table->json('raw')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rec_ab_logs')) {
            Schema::create('rec_ab_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('device_id');
                $table->string('variant', 1);
                $table->string('placement')->nullable();
                $table->timestamps();

                $table->index(['variant', 'created_at']);
            });
        }

        if (! Schema::hasTable('rec_clicks')) {
            Schema::create('rec_clicks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('movie_id')->constrained('movies')->cascadeOnDelete();
                $table->string('device_id')->nullable();
                $table->string('variant', 1);
                $table->string('placement');
                $table->timestamps();

                $table->index(['placement', 'created_at']);
                $table->index(['variant', 'created_at']);
            });
        }

        if (! Schema::hasTable('device_history')) {
            Schema::create('device_history', function (Blueprint $table): void {
                $table->id();
                $table->string('device_id');
                $table->string('path');
                $table->timestamp('viewed_at');
                $table->timestamps();

                $table->index(['device_id', 'viewed_at']);
            });
        }

        if (! Schema::hasTable('ssr_metrics')) {
            Schema::create('ssr_metrics', function (Blueprint $table): void {
                $table->id();
                $table->string('path');
                $table->unsignedInteger('score');
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['path', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ssr_metrics');
        Schema::dropIfExists('device_history');
        Schema::dropIfExists('rec_clicks');
        Schema::dropIfExists('rec_ab_logs');
        Schema::dropIfExists('movies');
    }
};
