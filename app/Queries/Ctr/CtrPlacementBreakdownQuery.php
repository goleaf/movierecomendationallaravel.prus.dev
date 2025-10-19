<?php

declare(strict_types=1);

namespace App\Queries\Ctr;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CtrPlacementBreakdownQuery
{
    /**
     * @param  list<string>  $placements
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, array $placements = [], ?string $variant = null): Builder
    {
        $range = [
            $from->startOfDay()->format('Y-m-d H:i:s'),
            $to->endOfDay()->format('Y-m-d H:i:s'),
        ];

        $impressions = DB::table('rec_ab_logs as logs')
            ->selectRaw('logs.placement, logs.variant, count(*) as impressions')
            ->whereBetween('logs.created_at', $range)
            ->when($placements !== [], static fn (Builder $query) => $query->whereIn('logs.placement', $placements))
            ->when($variant !== null, static fn (Builder $query) => $query->where('logs.variant', $variant))
            ->groupBy('logs.placement', 'logs.variant');

        $clicks = DB::table('rec_clicks as clicks')
            ->selectRaw('clicks.placement, clicks.variant, count(*) as clicks')
            ->whereBetween('clicks.created_at', $range)
            ->when($placements !== [], static fn (Builder $query) => $query->whereIn('clicks.placement', $placements))
            ->when($variant !== null, static fn (Builder $query) => $query->where('clicks.variant', $variant))
            ->groupBy('clicks.placement', 'clicks.variant');

        $left = DB::query()
            ->fromSub($impressions, 'impressions')
            ->leftJoinSub($clicks, 'clicks', static function ($join): void {
                $join->on('clicks.placement', '=', 'impressions.placement');
                $join->on('clicks.variant', '=', 'impressions.variant');
            })
            ->selectRaw('impressions.placement, impressions.variant, impressions.impressions, coalesce(clicks.clicks, 0) as clicks');

        $right = DB::query()
            ->fromSub($clicks, 'clicks')
            ->leftJoinSub($impressions, 'impressions', static function ($join): void {
                $join->on('impressions.placement', '=', 'clicks.placement');
                $join->on('impressions.variant', '=', 'clicks.variant');
            })
            ->whereNull('impressions.placement')
            ->selectRaw('clicks.placement, clicks.variant, coalesce(impressions.impressions, 0) as impressions, clicks.clicks');

        $combined = $left->unionAll($right);

        return DB::query()
            ->fromSub($combined, 'placement_breakdown')
            ->select('placement_breakdown.placement', 'placement_breakdown.variant', 'placement_breakdown.impressions', 'placement_breakdown.clicks')
            ->orderBy('placement_breakdown.placement')
            ->orderBy('placement_breakdown.variant');
    }
}
