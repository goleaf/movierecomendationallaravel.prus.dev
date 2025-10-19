<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class Slug
{
    public static function from(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        $slug = Str::slug($string);

        if ($slug === '') {
            return null;
        }

        return preg_match('/[a-z]/', $slug) === 1 ? $slug : null;
    }

    /**
     * @param  iterable<mixed>  $values
     * @return array<int, string>
     */
    public static function canonicalize(iterable $values): array
    {
        $unique = [];

        foreach ($values as $value) {
            $slug = self::from($value);

            if ($slug === null) {
                continue;
            }

            $unique[$slug] = $slug;
        }

        ksort($unique, SORT_STRING);

        return array_values($unique);
    }
}
