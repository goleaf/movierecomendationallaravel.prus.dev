<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table): void {
            $table->index(['type', 'year'], 'movies_type_year_index');
            $table->index(['imdb_votes' => 'desc', 'imdb_rating' => 'desc'], 'movies_imdb_votes_rating_desc_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX movies_genres_gin_index ON movies USING GIN ((genres::jsonb));');
        }
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table): void {
            $table->dropIndex('movies_type_year_index');
            $table->dropIndex('movies_imdb_votes_rating_desc_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS movies_genres_gin_index;');
        }
    }
};
