<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

final class AnalyticsFilters
{
    private const MIN_YEAR = 1870;

    private const MAX_YEAR = 2100;

    private const MIN_DAYS = 1;

    private const MAX_DAYS = 30;

    private const DEFAULT_DAYS = 7;

    /**
     * @var list<string>
     */
    private const PLACEMENTS = ['home', 'show', 'trends'];

    /**
     * @var list<string>
     */
    private const VARIANTS = ['A', 'B'];

    /**
     * @return array{from: string, to: string}
     */
    public static function normalizeDateRange(
        mixed $from,
        mixed $to,
        CarbonImmutable $defaultFrom,
        CarbonImmutable $defaultTo,
    ): array {
        return [
            'from' => self::normalizeDate($from, $defaultFrom),
            'to' => self::normalizeDate($to, $defaultTo),
        ];
    }

    public static function clampDays(mixed $value): int
    {
        $days = (int) ($value ?? self::DEFAULT_DAYS);

        if ($days < self::MIN_DAYS) {
            return self::MIN_DAYS;
        }

        if ($days > self::MAX_DAYS) {
            return self::MAX_DAYS;
        }

        return $days;
    }

    public static function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    public static function normalizePlacement(mixed $value): ?string
    {
        $placement = self::normalizeNullableString($value);

        if ($placement === null) {
            return null;
        }

        return in_array($placement, self::PLACEMENTS, true) ? $placement : null;
    }

    public static function normalizeVariant(mixed $value): ?string
    {
        $variant = self::normalizeNullableString($value);

        if ($variant === null) {
            return null;
        }

        return in_array($variant, self::VARIANTS, true) ? $variant : null;
    }

    /**
     * @return array{from: int|null, to: int|null}
     */
    public static function normalizeYearRange(mixed $from, mixed $to): array
    {
        $yearFrom = self::normalizeYear($from);
        $yearTo = self::normalizeYear($to);

        if ($yearFrom !== null && $yearTo !== null && $yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        return ['from' => $yearFrom, 'to' => $yearTo];
    }

    public static function allowedPlacements(): array
    {
        return self::PLACEMENTS;
    }

    public static function allowedVariants(): array
    {
        return self::VARIANTS;
    }

    private static function normalizeDate(mixed $value, CarbonImmutable $fallback): string
    {
        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                // Ignore invalid values and fall back
            }
        }

        return $fallback->format('Y-m-d');
    }

    private static function normalizeYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $year = (int) $value;

        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            return null;
        }

        return $year;
    }
}
