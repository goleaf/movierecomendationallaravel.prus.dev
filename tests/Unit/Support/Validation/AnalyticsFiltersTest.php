<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Validation;

use App\Support\Validation\AnalyticsFilters;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsFiltersTest extends TestCase
{
    #[Test]
    public function it_clamps_days_within_expected_window(): void
    {
        self::assertSame(
            AnalyticsFilters::MIN_RANGE_DAYS,
            AnalyticsFilters::clampDays(0)
        );

        self::assertSame(
            AnalyticsFilters::MAX_RANGE_DAYS,
            AnalyticsFilters::clampDays(99)
        );

        self::assertSame(
            AnalyticsFilters::DEFAULT_RANGE_DAYS,
            AnalyticsFilters::clampDays(null)
        );
    }

    #[Test]
    public function it_swaps_year_bounds_when_inverted(): void
    {
        $result = AnalyticsFilters::normalizeYearBounds(2025, 2020);

        self::assertSame(2020, $result['from']);
        self::assertSame(2025, $result['to']);
    }

    #[Test]
    public function it_rejects_unknown_placements(): void
    {
        self::assertNull(AnalyticsFilters::normalizePlacement('unknown'));
        self::assertSame('home', AnalyticsFilters::normalizePlacement('HOME'));
    }

    #[Test]
    public function it_returns_translated_options_for_filters(): void
    {
        $placementOptions = AnalyticsFilters::placementOptions();
        $variantOptions = AnalyticsFilters::variantOptions();

        self::assertSame(trans('admin.ctr.filters.placements.home'), $placementOptions['home']);
        self::assertSame(trans('admin.ctr.filters.variants.a'), $variantOptions['a']);
    }

    #[Test]
    public function it_normalizes_date_ranges_and_clamps_span(): void
    {
        $range = AnalyticsFilters::normalizeDateRange('2025-04-10', '2025-03-01');

        self::assertInstanceOf(CarbonImmutable::class, $range['from']);
        self::assertInstanceOf(CarbonImmutable::class, $range['to']);
        self::assertTrue($range['from']->lessThanOrEqualTo($range['to']));
        self::assertSame(30, (int) ($range['from']->diffInDays($range['to']) + 1));
        self::assertSame('2025-03-12', $range['from']->format('Y-m-d'));
        self::assertSame('2025-04-10', $range['to']->format('Y-m-d'));
    }
}
