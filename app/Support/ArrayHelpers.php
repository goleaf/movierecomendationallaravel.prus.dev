<?php

declare(strict_types=1);

namespace App\Support;

use Closure;

final class ArrayHelpers
{
    /**
     * Search through a list of associative arrays and return the first key whose
     * given column matches the provided needle.
     *
     * @param  array<int|string, array<string|int, mixed>>  $haystack
     */
    public static function columnSearch(array $haystack, string|int $columnKey, mixed $needle, bool $strict = true): int|string|null
    {
        $predicate = self::buildPredicate($needle, $strict);

        foreach ($haystack as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (array_key_exists($columnKey, $row) && $predicate($row[$columnKey])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Recursively search a multidimensional array for the first element whose
     * given key matches the provided value (or predicate).
     *
     * @param  array<mixed>  $haystack
     * @return array<string|int, mixed>|null
     */
    public static function recursiveFindByKeyValue(array $haystack, string|int $key, mixed $expected, bool $strict = true): ?array
    {
        $predicate = self::buildPredicate($expected, $strict);

        foreach ($haystack as $value) {
            if (! is_array($value)) {
                continue;
            }

            if (array_key_exists($key, $value) && $predicate($value[$key] ?? null)) {
                return $value;
            }

            $found = self::recursiveFindByKeyValue($value, $key, $expected, $strict);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Determine whether a multidimensional array contains the provided value or
     * matches the predicate.
     *
     * @param  array<mixed>  $haystack
     */
    public static function recursiveContains(array $haystack, mixed $needle, bool $strict = true): bool
    {
        $predicate = self::buildPredicate($needle, $strict);

        foreach ($haystack as $value) {
            if ($predicate($value)) {
                return true;
            }

            if (is_array($value) && self::recursiveContains($value, $needle, $strict)) {
                return true;
            }
        }

        return false;
    }

    private static function buildPredicate(mixed $expected, bool $strict): Closure
    {
        if ($expected instanceof Closure) {
            return static fn (mixed $value): bool => (bool) $expected($value);
        }

        if (is_object($expected) && method_exists($expected, '__invoke')) {
            return static fn (mixed $value): bool => (bool) $expected($value);
        }

        if ($strict) {
            return static fn (mixed $value): bool => $value === $expected;
        }

        return static fn (mixed $value): bool => $value == $expected;
    }
}
