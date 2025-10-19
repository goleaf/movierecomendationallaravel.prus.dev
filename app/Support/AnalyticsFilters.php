<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

final class AnalyticsFilters
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function parseDateRange(
        ?string $from,
        ?string $to,
        CarbonImmutable $defaultFrom,
        CarbonImmutable $defaultTo,
    ): array {
        $fromDate = self::parseDate($from, $defaultFrom);
        $toDate = self::parseDate($to, $defaultTo);

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    /**
     * @return list<string>
     */
    public static function placementCodes(): array
    {
        return ['home', 'show', 'trends'];
    }

    /**
     * @return list<string>
     */
    public static function variantCodes(): array
    {
        return ['A', 'B'];
    }

    /**
     * @return array<string, string>
     */
    public static function placementOptions(): array
    {
        $allLabel = self::translate('admin.ctr.filters.placements.all', 'All placements');

        $options = ['' => $allLabel];
        foreach (self::placementCodes() as $placement) {
            $options[$placement] = self::placementLabel($placement);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function variantOptions(): array
    {
        $allLabel = self::translate('admin.ctr.filters.variants.all', 'All variants');

        $options = ['' => $allLabel];
        foreach (self::variantCodes() as $code) {
            $options[$code] = self::variantLabel($code);
        }

        return $options;
    }

    public static function placementLabel(string $placement, bool $translated = true): string
    {
        if (! $translated) {
            return $placement;
        }

        return self::translate("admin.ctr.filters.placements.$placement", ucfirst($placement));
    }

    public static function variantLabel(string $variant): string
    {
        $key = 'admin.ctr.filters.variants.'.strtolower($variant);

        return self::translate($key, 'Variant '.strtoupper($variant));
    }

    private static function parseDate(?string $value, CarbonImmutable $fallback): CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public static function translate(string $key, string $fallback): string
    {
        $translated = __($key);

        if (is_array($translated)) {
            $translated = reset($translated) ?: null;
        }

        if (! is_string($translated) || $translated === '' || $translated === $key) {
            return $fallback;
        }

        return $translated;
    }
}
