<?php

declare(strict_types=1);

namespace App\Queries\Ctr;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CtrVariantSummaryQuery
{
    public function build(CarbonImmutable $from, CarbonImmutable $to, ?string $placement, ?string $variant): Builder
    {
        $range = [
            $from->startOfDay()->format('Y-m-d H:i:s'),
            $to->endOfDay()->format('Y-m-d H:i:s'),
        ];

        $impressions = DB::table('rec_ab_logs as logs')
            ->selectRaw('logs.variant, count(*) as impressions')
            ->whereBetween('logs.created_at', $range)
            ->when($placement !== null, static fn (Builder $query) => $query->where('logs.placement', $placement))
            ->when($variant !== null, static fn (Builder $query) => $query->where('logs.variant', $variant))
            ->groupBy('logs.variant');

        $clicks = DB::table('rec_clicks as clicks')
            ->selectRaw('clicks.variant, count(*) as clicks')
            ->whereBetween('clicks.created_at', $range)
            ->when($placement !== null, static fn (Builder $query) => $query->where('clicks.placement', $placement))
            ->when($variant !== null, static fn (Builder $query) => $query->where('clicks.variant', $variant))
            ->groupBy('clicks.variant');

        return DB::query()
            ->fromSub($impressions, 'impressions')
            ->leftJoinSub($clicks, 'clicks', 'clicks.variant', '=', 'impressions.variant')
            ->selectRaw('impressions.variant as variant, impressions.impressions, coalesce(clicks.clicks, 0) as clicks')
            ->orderBy('impressions.variant');
    }
}
