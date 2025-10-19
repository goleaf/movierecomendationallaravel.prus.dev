<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_TABLE = 'movie_items';

    private const TARGET_TABLE = 'movies';

    private const INDEX_RENAMES = [
        'movie_items_imdb_tt_unique' => [
            'new' => 'movies_imdb_tt_unique',
            'type' => 'unique',
            'columns' => ['imdb_tt'],
        ],
        'movie_items_type_year_index' => [
            'new' => 'movies_type_year_index',
            'type' => 'index',
            'columns' => ['type', 'year'],
        ],
        'movie_items_imdb_votes_rating_desc_index' => [
            'new' => 'movies_imdb_votes_rating_desc_index',
            'type' => 'index',
            'columns' => ['imdb_votes' => 'desc', 'imdb_rating' => 'desc'],
        ],
        'movie_items_genres_gin_index' => [
            'new' => 'movies_genres_gin_index',
            'type' => 'gin',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::LEGACY_TABLE) || Schema::hasTable(self::TARGET_TABLE)) {
            return;
        }

        Schema::rename(self::LEGACY_TABLE, self::TARGET_TABLE);

        $this->renameIndexes(forward: true);
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TARGET_TABLE) || Schema::hasTable(self::LEGACY_TABLE)) {
            return;
        }

        $this->renameIndexes(forward: false);

        Schema::rename(self::TARGET_TABLE, self::LEGACY_TABLE);
    }

    private function renameIndexes(bool $forward): void
    {
        foreach (self::INDEX_RENAMES as $legacy => $details) {
            $from = $forward ? $legacy : $details['new'];
            $to = $forward ? $details['new'] : $legacy;

            $this->renameIndex($from, $to, $details);
        }
    }

    private function renameIndex(string $from, string $to, array $details): void
    {
        $table = self::TARGET_TABLE;

        if (! $this->indexExists($table, $from)) {
            return;
        }

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        $wrappedTable = $this->wrap($table);
        $wrappedFrom = $this->wrap($from);
        $wrappedTo = $this->wrap($to);

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                $connection->statement("ALTER TABLE {$wrappedTable} RENAME INDEX {$wrappedFrom} TO {$wrappedTo}");
                break;
            case 'pgsql':
                $connection->statement("ALTER INDEX {$wrappedFrom} RENAME TO {$wrappedTo}");
                break;
            case 'sqlite':
                $this->recreateSqliteIndex($table, $from, $to, $details);
                break;
            default:
                throw new \RuntimeException("Index rename not supported for driver [{$driver}].");
        }
    }

    private function wrap(string $value): string
    {
        return Schema::getConnection()->getQueryGrammar()->wrap($value);
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            $list = collect($connection->select("PRAGMA index_list('{$table}')"));

            return $list->contains(static fn ($row) => ($row->name ?? null) === $index);
        }

        $schemaManager = $connection->getDoctrineSchemaManager();

        return array_key_exists($index, $schemaManager->listTableIndexes($table));
    }

    private function recreateSqliteIndex(string $table, string $from, string $to, array $details): void
    {
        if (($details['type'] ?? 'index') === 'gin') {
            return;
        }

        $connection = Schema::getConnection();
        $list = collect($connection->select("PRAGMA index_list('{$table}')"));
        $index = $list->firstWhere('name', $from);

        if ($index === null) {
            return;
        }

        $connection->statement('DROP INDEX "'.str_replace('"', '""', $from).'"');

        $columns = $details['columns'] ?? [];

        if ($columns === []) {
            return;
        }

        $columnsSql = collect($columns)
            ->map(static function ($order, $column): string {
                if (is_int($column)) {
                    $column = $order;
                    $order = 'asc';
                }

                $direction = strtolower((string) $order) === 'desc' ? ' DESC' : '';

                return '"'.str_replace('"', '""', (string) $column).'"'.$direction;
            })
            ->implode(', ');

        if ($columnsSql === '') {
            return;
        }

        $unique = ($details['type'] ?? 'index') === 'unique' ? 'UNIQUE ' : '';

        $connection->statement(sprintf(
            'CREATE %sINDEX "%s" ON "%s" (%s)',
            $unique,
            str_replace('"', '""', $to),
            str_replace('"', '""', $table),
            $columnsSql
        ));
    }
};
