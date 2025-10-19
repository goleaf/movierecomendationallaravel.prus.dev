<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Analytics\CtrAnalyticsService;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

class CtrBarsWidget extends Widget
{
    protected static string $view = 'filament.widgets.ctr_bars';

    protected function getViewData(): array
    {
        $service = app(CtrAnalyticsService::class);
        $from = CarbonImmutable::now()->subDays(7);
        $to = CarbonImmutable::now();

        return [
            'svg' => $service->buildPlacementCtrSvg($from, $to),
        ];
    }
}
