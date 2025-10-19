<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ArrayHelpers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayHelpers::class)]
final class ArrayHelpersTest extends TestCase
{
    public function test_column_search_returns_matching_key(): void
    {
        $recommendations = [
            'alpha' => ['variant' => 'A', 'weight' => 0.6],
            'beta' => ['variant' => 'B', 'weight' => 0.4],
            ['variant' => 'C', 'weight' => 0.0],
        ];

        self::assertSame('beta', ArrayHelpers::columnSearch($recommendations, 'variant', 'B'));
        self::assertNull(ArrayHelpers::columnSearch($recommendations, 'variant', 'Z'));
    }

    public function test_recursive_find_by_key_value_matches_nested_array(): void
    {
        $payload = [
            'A' => [
                'movies' => [
                    ['id' => 10, 'score' => 0.72],
                    ['id' => 11, 'score' => 0.54],
                ],
            ],
            'B' => [
                'movies' => [
                    ['id' => 12, 'score' => 0.93],
                    ['id' => 13, 'score' => 0.41],
                ],
            ],
        ];

        self::assertSame(
            ['id' => 12, 'score' => 0.93],
            ArrayHelpers::recursiveFindByKeyValue($payload, 'id', 12),
        );

        self::assertSame(
            ['id' => 12, 'score' => 0.93],
            ArrayHelpers::recursiveFindByKeyValue(
                $payload,
                'score',
                static fn (mixed $value): bool => is_numeric($value) && $value > 0.9,
            ),
        );

        self::assertNull(ArrayHelpers::recursiveFindByKeyValue($payload, 'id', 99));
    }

    public function test_recursive_contains_handles_complex_predicates(): void
    {
        $data = [
            [
                'variant' => 'A',
                'movies' => [
                    ['id' => 20, 'title' => 'Arrival', 'score' => 0.81],
                    ['id' => 21, 'title' => 'Gravity', 'score' => 0.64],
                ],
            ],
            [
                'variant' => 'B',
                'movies' => [
                    ['id' => 22, 'title' => 'Tenet', 'score' => 0.71],
                    ['id' => 23, 'title' => 'Dune', 'score' => 0.93],
                ],
            ],
        ];

        self::assertTrue(
            ArrayHelpers::recursiveContains(
                $data,
                static fn (mixed $value): bool => is_array($value)
                    && ($value['title'] ?? null) === 'Dune',
            ),
        );

        self::assertFalse(
            ArrayHelpers::recursiveContains(
                $data,
                static fn (mixed $value): bool => is_array($value)
                    && (($value['score'] ?? 0) > 0.95),
            ),
        );

        self::assertTrue(ArrayHelpers::recursiveContains($data, 'B'));
        self::assertFalse(ArrayHelpers::recursiveContains($data, 'Z'));
    }
}
