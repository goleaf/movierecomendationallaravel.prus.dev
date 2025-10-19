<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analytics\CtrDailySnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class AggregateCtrDailySnapshotsCommand extends Command
{
    protected $signature = 'analytics:aggregate-ctr-snapshots {--date=} {--from=} {--to=}';

    protected $description = 'Aggregate daily CTR snapshots from recommendation logs and clicks.';

    public function handle(CtrDailySnapshotService $service): int
    {
        $dateOption = $this->option('date');
        $fromOption = $this->option('from');
        $toOption = $this->option('to');

        if ($dateOption !== null) {
            $from = CarbonImmutable::parse($dateOption)->startOfDay();
            $to = $from;
        } else {
            $from = $fromOption !== null
                ? CarbonImmutable::parse($fromOption)->startOfDay()
                : CarbonImmutable::yesterday()->startOfDay();

            $to = $toOption !== null
                ? CarbonImmutable::parse($toOption)->startOfDay()
                : ($fromOption !== null ? $from : $from);
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $service->aggregateRange($from, $to);

        $this->info(sprintf(
            'Aggregated CTR snapshots from %s to %s',
            $from->toDateString(),
            $to->toDateString()
        ));

        return static::SUCCESS;
    }
}
