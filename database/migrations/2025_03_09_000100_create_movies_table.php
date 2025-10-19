<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('movies')) {
            return;
        }

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

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
