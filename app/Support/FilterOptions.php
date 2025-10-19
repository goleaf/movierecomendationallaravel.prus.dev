<?php

declare(strict_types=1);

namespace App\Support;

final class FilterOptions
{
    /**
     * @return array<int, string>
     */
    public static function placements(): array
    {
        return ['home', 'show', 'trends', 'unknown'];
    }

    /**
     * @return array<int, string>
     */
    public static function variants(): array
    {
        return ['A', 'B', 'mixed', 'fallback', 'unknown'];
    }
}
