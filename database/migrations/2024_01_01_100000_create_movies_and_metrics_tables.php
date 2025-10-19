<?php

declare(strict_types=1);

use App\Models\Movie;
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
        Schema::create('movies', function (Blueprint $table): void {
            $table->id();
            $table->string('imdb_tt')->unique();
            $table->string('title');
            $table->text('plot')->nullable();
            $table->string('type', 20);
            $table->unsignedSmallInteger('year')->nullable();
            $table->date('release_date')->nullable();
            $table->decimal('imdb_rating', 3, 1)->nullable();
            $table->integer('imdb_votes')->nullable();
            $table->unsignedSmallInteger('runtime_min')->nullable();
            $table->json('genres')->nullable();
            $table->string('poster_url')->nullable();
            $table->string('backdrop_url')->nullable();
            $table->json('translations')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        Schema::create('rec_ab_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Movie::class)->constrained()->cascadeOnDelete();
            $table->string('device_id', 64);
            $table->string('placement', 32);
            $table->string('variant', 8);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique([
                'device_id',
                'placement',
                'variant',
                'movie_id',
                'created_at',
            ], 'rec_ab_logs_unique_event');
        });

        Schema::create('rec_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Movie::class)->constrained()->cascadeOnDelete();
            $table->string('device_id', 64);
            $table->string('placement', 32);
            $table->string('variant', 8);
            $table->unsignedTinyInteger('position')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            $table->unique([
                'device_id',
                'placement',
                'variant',
                'movie_id',
                'created_at',
            ], 'rec_clicks_unique_event');
        });

        Schema::create('device_history', function (Blueprint $table): void {
            $table->id();
            $table->string('device_id');
            $table->string('page', 32);
            $table->foreignIdFor(Movie::class)->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamps();
        });

        Schema::create('ssr_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('path');
            $table->unsignedTinyInteger('score');
            $table->unsignedInteger('size');
            $table->unsignedInteger('meta_count');
            $table->unsignedInteger('og_count');
            $table->unsignedInteger('ldjson_count');
            $table->unsignedInteger('img_count');
            $table->unsignedInteger('blocking_scripts');
            $table->unsignedInteger('first_byte_ms')->default(0);
            $table->timestamps();
        });
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
