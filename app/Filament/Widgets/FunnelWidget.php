<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\CtrAnalyticsService;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

class FunnelWidget extends Widget
{
    protected static string $view = 'filament.widgets.funnel';

    protected static ?string $heading = 'Funnels (7 дней)';

    protected function getViewData(): array
    {
        $from = CarbonImmutable::now()->subDays(7);
        $to = CarbonImmutable::now();

        $service = app(CtrAnalyticsService::class);
        $rows = $service->funnels($from, $to);

        return [
            'heading' => $this->getHeading(),
            'rows' => $rows,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ];
    }
}
