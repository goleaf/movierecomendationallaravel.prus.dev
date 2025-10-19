<?php

declare(strict_types=1);

namespace App\Queries\Ctr;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CtrDailyMetricsQuery
{
    public function build(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $range = [
            $from->startOfDay()->format('Y-m-d H:i:s'),
            $to->endOfDay()->format('Y-m-d H:i:s'),
        ];

        $impressions = DB::table('rec_ab_logs as logs')
            ->selectRaw('date(logs.created_at) as day, logs.variant, count(*) as impressions')
            ->whereBetween('logs.created_at', $range)
            ->groupByRaw('date(logs.created_at), logs.variant');

        $clicks = DB::table('rec_clicks as clicks')
            ->selectRaw('date(clicks.created_at) as day, clicks.variant, count(*) as clicks')
            ->whereBetween('clicks.created_at', $range)
            ->groupByRaw('date(clicks.created_at), clicks.variant');

        $left = DB::query()
            ->fromSub($impressions, 'impressions')
            ->leftJoinSub($clicks, 'clicks', static function ($join): void {
                $join->on('clicks.day', '=', 'impressions.day');
                $join->on('clicks.variant', '=', 'impressions.variant');
            })
            ->selectRaw('impressions.day, impressions.variant, impressions.impressions, coalesce(clicks.clicks, 0) as clicks');

        $right = DB::query()
            ->fromSub($clicks, 'clicks')
            ->leftJoinSub($impressions, 'impressions', static function ($join): void {
                $join->on('impressions.day', '=', 'clicks.day');
                $join->on('impressions.variant', '=', 'clicks.variant');
            })
            ->whereNull('impressions.day')
            ->selectRaw('clicks.day, clicks.variant, coalesce(impressions.impressions, 0) as impressions, clicks.clicks');

        $combined = $left->unionAll($right);

        return DB::query()
            ->fromSub($combined, 'daily_metrics')
            ->select('daily_metrics.day', 'daily_metrics.variant', 'daily_metrics.impressions', 'daily_metrics.clicks')
            ->orderBy('daily_metrics.day')
            ->orderBy('daily_metrics.variant');
    }
}
