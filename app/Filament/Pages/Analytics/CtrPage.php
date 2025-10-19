<?php

declare(strict_types=1);

namespace App\Filament\Pages\Analytics;

use App\Services\Analytics\CtrAnalyticsService;
use App\Support\AnalyticsFilters;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Jacobtims\InlineDateTimePicker\Forms\Components\InlineDateTimePicker;

class CtrPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.analytics.ctr';

    protected static ?string $navigationLabel = 'CTR';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $slug = 'ctr';

    /** @var array{from: string, to: string, placement: string, variant: string} */
    public array $filters = [
        'from' => '',
        'to' => '',
        'placement' => '',
        'variant' => '',
    ];

    /** @var array<int, array<string, mixed>> */
    public array $summary = [];

    /** @var array<string, int> */
    public array $placementClicks = [];

    /** @var array<int, array<string, mixed>> */
    public array $funnels = [];

    public ?string $lineSvg = null;

    public ?string $barsSvg = null;

    /** @var array<string, string> */
    public array $placementOptions = [];

    /** @var array<string, string> */
    public array $variantOptions = [];

    public function mount(): void
    {
        $defaultTo = CarbonImmutable::now();
        $defaultFrom = $defaultTo->subDays(7);

        [$from, $to] = AnalyticsFilters::parseDateRange(null, null, $defaultFrom, $defaultTo);

        $this->filters = [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'placement' => '',
            'variant' => '',
        ];
        $this->placementOptions = AnalyticsFilters::placementOptions();
        $this->variantOptions = AnalyticsFilters::variantOptions();

        $this->form->fill($this->filters);
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $defaultTo = CarbonImmutable::now();
        $defaultFrom = $defaultTo->subDays(7);

        [$fromDate, $toDate] = AnalyticsFilters::parseDateRange(
            $this->filters['from'] ?? null,
            $this->filters['to'] ?? null,
            $defaultFrom,
            $defaultTo,
        );

        $service = app(CtrAnalyticsService::class);

        $summary = $service->variantSummary(
            $fromDate,
            $toDate,
            $this->filters['placement'] ?: null,
            $this->filters['variant'] ?: null,
        );
        $this->summary = $summary['summary'];
        $this->placementClicks = $summary['placementClicks'];
        $this->funnels = $service->funnels($fromDate, $toDate);
        $this->lineSvg = $service->buildDailyCtrSvg($fromDate, $toDate);
        $this->barsSvg = $service->buildPlacementCtrSvg($fromDate, $toDate);

        $this->filters['from'] = $fromDate->format('Y-m-d');
        $this->filters['to'] = $toDate->format('Y-m-d');
        $this->form->fill($this->filters);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->extraAttributes([
                'class' => 'grid gap-4 sm:grid-cols-2 lg:grid-cols-4',
                'role' => 'group',
                'aria-label' => __('admin.ctr.filters.aria_label'),
            ])
            ->schema([
                InlineDateTimePicker::make('from')
                    ->label(__('admin.ctr.filters.from'))
                    ->time(false)
                    ->seconds(false)
                    ->afterStateUpdated(function (?string $state): void {
                        $this->filters['from'] = $state ?? '';
                        $this->refreshData();
                    }),
                InlineDateTimePicker::make('to')
                    ->label(__('admin.ctr.filters.to'))
                    ->time(false)
                    ->seconds(false)
                    ->afterStateUpdated(function (?string $state): void {
                        $this->filters['to'] = $state ?? '';
                        $this->refreshData();
                    }),
                Select::make('placement')
                    ->label(__('admin.ctr.filters.placement'))
                    ->options(fn (): array => $this->placementOptions)
                    ->searchable()
                    ->afterStateUpdated(function (?string $state): void {
                        $this->filters['placement'] = $state ?? '';
                        $this->refreshData();
                    }),
                Select::make('variant')
                    ->label(__('admin.ctr.filters.variant'))
                    ->options(fn (): array => $this->variantOptions)
                    ->searchable()
                    ->afterStateUpdated(function (?string $state): void {
                        $this->filters['variant'] = $state ?? '';
                        $this->refreshData();
                    }),
                Actions::make([
                    Action::make('refresh')
                        ->label(__('admin.ctr.filters.refresh'))
                        ->color('primary')
                        ->action(function (): void {
                            $this->refreshData();
                        }),
                ])
                    ->columnSpanFull()
                    ->alignEnd(),
            ]);
    }
}
