<?php

declare(strict_types=1);

namespace App\Queries\Ctr;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CtrFunnelsQuery
{
    /**
     * @param  list<string>  $placements
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, array $placements = []): Builder
    {
        $range = [
            $from->startOfDay()->format('Y-m-d H:i:s'),
            $to->endOfDay()->format('Y-m-d H:i:s'),
        ];

        $impressions = DB::table('rec_ab_logs as logs')
            ->selectRaw('logs.placement, count(*) as impressions')
            ->whereBetween('logs.created_at', $range)
            ->when($placements !== [], static fn (Builder $query) => $query->whereIn('logs.placement', $placements))
            ->groupBy('logs.placement');

        $clicks = Schema::hasTable('rec_clicks')
            ? DB::table('rec_clicks as clicks')
                ->selectRaw('clicks.placement, count(*) as clicks')
                ->whereBetween('clicks.created_at', $range)
                ->when($placements !== [], static fn (Builder $query) => $query->whereIn('clicks.placement', $placements))
                ->groupBy('clicks.placement')
            : DB::query()->selectRaw('NULL as placement, 0 as clicks')->whereRaw('1 = 0');

        $views = Schema::hasTable('device_history')
            ? DB::table('device_history as views')
                ->selectRaw('views.page as placement, count(*) as views')
                ->whereBetween('views.viewed_at', $range)
                ->when($placements !== [], static fn (Builder $query) => $query->whereIn('views.page', $placements))
                ->groupBy('views.page')
            : DB::query()->selectRaw('NULL as placement, 0 as views')->whereRaw('1 = 0');

        $base = DB::query()
            ->fromSub($impressions, 'impressions')
            ->leftJoinSub($clicks, 'clicks', 'clicks.placement', '=', 'impressions.placement')
            ->leftJoinSub($views, 'views', 'views.placement', '=', 'impressions.placement')
            ->selectRaw('impressions.placement, impressions.impressions, coalesce(clicks.clicks, 0) as clicks, coalesce(views.views, 0) as views');

        $missing = DB::query()
            ->fromSub($clicks, 'clicks')
            ->leftJoinSub($impressions, 'impressions', 'impressions.placement', '=', 'clicks.placement')
            ->leftJoinSub($views, 'views', 'views.placement', '=', 'clicks.placement')
            ->whereNull('impressions.placement')
            ->selectRaw('clicks.placement, coalesce(impressions.impressions, 0) as impressions, clicks.clicks, coalesce(views.views, 0) as views');

        $viewOnly = DB::query()
            ->fromSub($views, 'views')
            ->leftJoinSub($impressions, 'impressions', 'impressions.placement', '=', 'views.placement')
            ->leftJoinSub($clicks, 'clicks', 'clicks.placement', '=', 'views.placement')
            ->whereNull('impressions.placement')
            ->whereNull('clicks.placement')
            ->selectRaw('views.placement, coalesce(impressions.impressions, 0) as impressions, coalesce(clicks.clicks, 0) as clicks, views.views');

        $combined = $base->unionAll($missing)->unionAll($viewOnly);

        return DB::query()
            ->fromSub($combined, 'funnels')
            ->select('funnels.placement', 'funnels.impressions', 'funnels.clicks', 'funnels.views')
            ->orderBy('funnels.placement');
    }
}
