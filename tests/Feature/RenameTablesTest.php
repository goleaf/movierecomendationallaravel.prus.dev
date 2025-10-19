<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RenameTablesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        Schema::dropAllTables();
        Schema::enableForeignKeyConstraints();
    }

    public function test_migration_renames_movie_items_table_and_indexes(): void
    {
        Schema::create('movie_items', function (Blueprint $table): void {
            $table->id();
            $table->string('imdb_tt')->unique('movie_items_imdb_tt_unique');
            $table->string('title');
            $table->string('type', 32)->default('movie');
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('imdb_votes')->nullable();
            $table->decimal('imdb_rating', 3, 1)->nullable();
            $table->timestamps();
        });

        Schema::table('movie_items', function (Blueprint $table): void {
            $table->index(['type', 'year'], 'movie_items_type_year_index');
            $table->index(['imdb_votes' => 'desc', 'imdb_rating' => 'desc'], 'movie_items_imdb_votes_rating_desc_index');
        });

        DB::table('movie_items')->insert([
            'imdb_tt' => 'tt1234567',
            'title' => 'Legacy Title',
            'type' => 'movie',
            'year' => 2024,
            'imdb_votes' => 12345,
            'imdb_rating' => 8.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2025_10_30_000000_rename_tables.php');
        $migration->up();

        $this->assertFalse(Schema::hasTable('movie_items'));
        $this->assertTrue(Schema::hasTable('movies'));

        $this->assertIndexMissing('movies', 'movie_items_imdb_tt_unique');
        $this->assertIndexExists('movies', 'movies_imdb_tt_unique');
        $this->assertIndexMissing('movies', 'movie_items_type_year_index');
        $this->assertIndexExists('movies', 'movies_type_year_index');
        $this->assertIndexMissing('movies', 'movie_items_imdb_votes_rating_desc_index');
        $this->assertIndexExists('movies', 'movies_imdb_votes_rating_desc_index');

        $this->assertSame('tt1234567', DB::table('movies')->value('imdb_tt'));

        $migration->down();

        $this->assertTrue(Schema::hasTable('movie_items'));
        $this->assertFalse(Schema::hasTable('movies'));
        $this->assertIndexExists('movie_items', 'movie_items_imdb_tt_unique');
        $this->assertIndexExists('movie_items', 'movie_items_type_year_index');
        $this->assertIndexExists('movie_items', 'movie_items_imdb_votes_rating_desc_index');

        $migration->up();

        $this->assertTrue(Schema::hasTable('movies'));
        $this->assertIndexExists('movies', 'movies_imdb_tt_unique');
        $this->assertIndexExists('movies', 'movies_type_year_index');
        $this->assertIndexExists('movies', 'movies_imdb_votes_rating_desc_index');
    }

    private function assertIndexExists(string $table, string $index): void
    {
        $this->assertContains($index, $this->listIndexes($table));
    }

    private function assertIndexMissing(string $table, string $index): void
    {
        $this->assertNotContains($index, $this->listIndexes($table));
    }

    /**
     * @return array<int, string>
     */
    private function listIndexes(string $table): array
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return collect($connection->select("PRAGMA index_list('{$table}')"))
                ->pluck('name')
                ->filter()
                ->values()
                ->all();
        }

        return array_keys($connection->getDoctrineSchemaManager()->listTableIndexes($table));
    }
}
