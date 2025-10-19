<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Queries\Ctr\CtrDailyMetricsQuery;
use App\Queries\Ctr\CtrFunnelsQuery;
use App\Queries\Ctr\CtrPlacementBreakdownQuery;
use App\Queries\Ctr\CtrVariantSummaryQuery;
use App\Queries\Trends\TrendingItemsQuery;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportsJoinsTest extends TestCase
{
    public function test_ctr_variant_summary_query_snapshot(): void
    {
        $query = app(CtrVariantSummaryQuery::class)
            ->build(CarbonImmutable::parse('2025-02-01', 'UTC'), CarbonImmutable::parse('2025-02-07', 'UTC'), 'home', null);

        $this->assertSqlSnapshot('tests/Feature/__snapshots__/ctr_variant_summary.sql', $query);
    }

    public function test_ctr_placement_breakdown_query_snapshot(): void
    {
        $query = app(CtrPlacementBreakdownQuery::class)
            ->build(CarbonImmutable::parse('2025-02-01', 'UTC'), CarbonImmutable::parse('2025-02-07', 'UTC'), ['home', 'trends']);

        $this->assertSqlSnapshot('tests/Feature/__snapshots__/ctr_placement_breakdown.sql', $query);
    }

    public function test_ctr_funnels_query_snapshot(): void
    {
        $query = app(CtrFunnelsQuery::class)
            ->build(CarbonImmutable::parse('2025-02-01', 'UTC'), CarbonImmutable::parse('2025-02-07', 'UTC'), ['home', 'trends']);

        $this->assertSqlSnapshot('tests/Feature/__snapshots__/ctr_funnels.sql', $query);
    }

    public function test_ctr_daily_metrics_query_snapshot(): void
    {
        $query = app(CtrDailyMetricsQuery::class)
            ->build(CarbonImmutable::parse('2025-02-01', 'UTC'), CarbonImmutable::parse('2025-02-07', 'UTC'));

        $this->assertSqlSnapshot('tests/Feature/__snapshots__/ctr_daily_metrics.sql', $query);
    }

    public function test_trends_rollups_query_snapshot(): void
    {
        $filters = [
            'type' => 'movie',
            'genre' => 'comedy',
            'year_from' => 2020,
            'year_to' => 2024,
        ];

        $query = app(TrendingItemsQuery::class)
            ->rollups(CarbonImmutable::parse('2025-02-01', 'UTC'), CarbonImmutable::parse('2025-02-07', 'UTC'), $filters);

        $this->assertSqlSnapshot('tests/Feature/__snapshots__/trends_rollups.sql', $query);
    }

    public function test_trends_clicks_query_snapshot(): void
    {
        $filters = [
            'type' => 'movie',
            'genre' => 'comedy',
            'year_from' => 2020,
            'year_to' => 2024,
        ];

        $query = app(TrendingItemsQuery::class)
            ->clicks(CarbonImmutable::parse('2025-02-01', 'UTC'), CarbonImmutable::parse('2025-02-07', 'UTC'), $filters);

        $this->assertSqlSnapshot('tests/Feature/__snapshots__/trends_clicks.sql', $query);
    }

    private function assertSqlSnapshot(string $path, Builder $builder): void
    {
        $sql = $builder->toSql();
        $bindings = array_map(fn ($value): string => $this->formatBinding($value), $builder->getBindings());
        $statement = Str::replaceArray('?', $bindings, $sql);

        $this->assertSnapshotEquals($path, $statement."\n");
    }

    private function formatBinding(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return "'".$value->format('Y-m-d H:i:s')."'";
        }

        if (is_string($value)) {
            return "'".str_replace("'", "''", $value)."'";
        }

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function assertSnapshotEquals(string $path, string $actual): void
    {
        $fullPath = base_path($path);

        if (! File::exists($fullPath)) {
            File::ensureDirectoryExists(dirname($fullPath));
            File::put($fullPath, $this->normalize($actual)."\n");
        }

        $expected = File::get($fullPath);
        $this->assertSame(
            $this->normalize($expected),
            $this->normalize($actual),
            sprintf('Snapshot mismatch for %s', $path)
        );
    }

    private function normalize(string $value): string
    {
        return trim(str_replace("\r\n", "\n", $value));
    }
}
