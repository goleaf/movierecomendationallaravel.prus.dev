<?php

namespace App\Support\Validation;

use Carbon\CarbonImmutable;

final class AnalyticsFilters
{
    /**
     * Default number of days applied when a range is missing or invalid.
     */
    public const DEFAULT_RANGE_DAYS = 7;

    /**
     * Minimum number of days that can be requested for analytics windows.
     */
    public const MIN_RANGE_DAYS = 1;

    /**
     * Maximum number of days that can be requested for analytics windows.
     */
    public const MAX_RANGE_DAYS = 30;

    /**
     * Canonical placement codes recognised by analytics dashboards.
     *
     * @var array<int, string>
     */
    private const PLACEMENTS = ['home', 'show', 'trends'];

    /**
     * Canonical variant codes recognised by analytics dashboards.
     *
     * @var array<int, string>
     */
    private const VARIANTS = ['a', 'b'];

    public static function parseDate(?string $value, ?CarbonImmutable $fallback = null): CarbonImmutable
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $fallback ??= CarbonImmutable::now($timezone)->startOfDay();

        if ($value === null) {
            return $fallback;
        }

        $value = trim($value);

        if ($value === '') {
            return $fallback;
        }

        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $value, $timezone);

        if ($parsed === false) {
            return $fallback;
        }

        return $parsed->startOfDay();
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public static function normalizeDateRange(?string $from, ?string $to, ?int $maxSpanDays = null): array
    {
        $maxSpanDays = $maxSpanDays !== null
            ? max(self::MIN_RANGE_DAYS, min(self::MAX_RANGE_DAYS, $maxSpanDays))
            : self::MAX_RANGE_DAYS;

        $timezone = (string) config('app.timezone', 'UTC');
        $defaultTo = CarbonImmutable::now($timezone)->startOfDay();
        $defaultFrom = $defaultTo->subDays(self::DEFAULT_RANGE_DAYS - 1);

        $fromDate = self::parseDate($from, $defaultFrom);
        $toDate = self::parseDate($to, $defaultTo);

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $span = $fromDate->diffInDays($toDate) + 1;

        if ($span > $maxSpanDays) {
            $fromDate = $toDate->subDays($maxSpanDays - 1);
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
        ];
    }

    public static function clampDays(?int $days, ?int $default = null): int
    {
        $default ??= self::DEFAULT_RANGE_DAYS;

        if ($days === null) {
            return $default;
        }

        return max(self::MIN_RANGE_DAYS, min(self::MAX_RANGE_DAYS, $days));
    }

    /**
     * @return array{from: ?int, to: ?int}
     */
    public static function normalizeYearBounds(mixed $from, mixed $to): array
    {
        $fromYear = self::normalizeYear($from);
        $toYear = self::normalizeYear($to);

        if ($fromYear !== null && $toYear !== null && $fromYear > $toYear) {
            [$fromYear, $toYear] = [$toYear, $fromYear];
        }

        return [
            'from' => $fromYear,
            'to' => $toYear,
        ];
    }

    public static function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    public static function allowedPlacements(): array
    {
        return self::PLACEMENTS;
    }

    public static function allowedVariants(): array
    {
        return self::VARIANTS;
    }

    public static function normalizePlacement(mixed $placement): ?string
    {
        $value = self::normalizeNullableString($placement);

        if ($value === null) {
            return null;
        }

        $value = strtolower($value);

        return in_array($value, self::PLACEMENTS, true) ? $value : null;
    }

    public static function normalizeVariant(mixed $variant): ?string
    {
        $value = self::normalizeNullableString($variant);

        if ($value === null) {
            return null;
        }

        $value = strtolower($value);

        return in_array($value, self::VARIANTS, true) ? $value : null;
    }

    /**
     * @return array<string, string>
     */
    public static function placementOptions(): array
    {
        $options = ['all' => trans('admin.ctr.filters.placements.all')];

        foreach (self::PLACEMENTS as $placement) {
            $options[$placement] = trans("admin.ctr.filters.placements.{$placement}");
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function variantOptions(): array
    {
        $options = ['all' => trans('admin.ctr.filters.variants.all')];

        foreach (self::VARIANTS as $variant) {
            $options[$variant] = trans("admin.ctr.filters.variants.{$variant}");
        }

        return $options;
    }

    private static function normalizeYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
