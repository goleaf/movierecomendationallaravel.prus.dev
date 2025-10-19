<?php

declare(strict_types=1);

namespace App\Reports;

use App\Queries\Trends\TrendingItemsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TrendsReport
{
    public function __construct(private readonly TrendingItemsQuery $query) {}

    /**
     * @param  array{type: string, genre: string, year_from: int, year_to: int}  $filters
     */
    public function rollupItems(CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        return $this->query->rollups($from, $to, $filters)->get();
    }

    /**
     * @param  array{type: string, genre: string, year_from: int, year_to: int}  $filters
     */
    public function clickItems(CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        return $this->query->clicks($from, $to, $filters)->get();
    }
}
