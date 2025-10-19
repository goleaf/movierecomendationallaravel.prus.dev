# Migration Notes

## Legacy table cleanup

The `2025_10_30_000000_rename_tables.php` migration renames the historical `movie_items` table to the canonical `movies` table when upgrading older databases. It also renames any legacy indexes so they match the new table name on MySQL, PostgreSQL, and SQLite. If the legacy table (or indexes) is missing, the migration skips gracefully.

To roll back, the migration restores the original index names before renaming the table back to `movie_items`, which keeps the schema reversible and preserves index definitions.
