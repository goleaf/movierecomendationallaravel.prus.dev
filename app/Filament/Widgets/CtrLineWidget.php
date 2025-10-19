<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\CtrAnalyticsService;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

class CtrLineWidget extends Widget
{
    protected string $view = 'filament.widgets.ctr_line';

    protected function getViewData(): array
    {
        $service = app(CtrAnalyticsService::class);
        $from = CarbonImmutable::now()->subDays(14);
        $to = CarbonImmutable::now();

        return [
            'svg' => $service->buildDailyCtrSvg($from, $to),
        ];
    }
}
