<?php

declare(strict_types=1);

namespace App\Filament\Pages\Analytics;

use App\Services\Analytics\TrendsAnalyticsService;
use App\Support\AnalyticsFilters;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Jacobtims\InlineDateTimePicker\Forms\Components\InlineDateTimePicker;

class TrendsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-line-square';

    protected static string $view = 'filament.analytics.trends';

    protected static ?string $navigationLabel = 'Trends';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $slug = 'trends';

    public bool $showAdvancedFilters = true;

    /** @var array<int, int> */
    public array $dayOptions = [3, 7, 14, 30];

    /** @var array<string, string> */
    public array $typeOptions = [];

    /** @var array{
     *     days: int,
     *     from: string,
     *     to: string,
     *     type: string,
     *     genre: string,
     *     year_from: string,
     *     year_to: string
     * }
     */
    public array $filters = [
        'days' => 7,
        'from' => '',
        'to' => '',
        'type' => '',
        'genre' => '',
        'year_from' => '',
        'year_to' => '',
    ];

    /** @var array<int, array{id: int, title: string, poster_url: ?string, year: ?int, type: ?string, imdb_rating: ?float, imdb_votes: ?int, clicks: ?int}> */
    public array $items = [];

    public function mount(): void
    {
        $this->typeOptions = [
            '' => __('admin.trends.type_placeholder'),
            'movie' => __('admin.trends.types.movie'),
            'series' => __('admin.trends.types.series'),
            'animation' => __('admin.trends.types.animation'),
        ];

        $defaultTo = CarbonImmutable::now();
        $defaultFrom = $defaultTo->subDays($this->filters['days']);

        [$from, $to] = AnalyticsFilters::parseDateRange(null, null, $defaultFrom, $defaultTo);

        $this->filters['from'] = $from->format('Y-m-d');
        $this->filters['to'] = $to->format('Y-m-d');

        $this->form->fill($this->filters);
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $defaultTo = CarbonImmutable::now();
        $days = (int) ($this->filters['days'] ?? 7);
        $defaultFrom = $defaultTo->subDays($days > 0 ? $days : 7);

        [$fromDate, $toDate] = AnalyticsFilters::parseDateRange(
            $this->filters['from'] ?? null,
            $this->filters['to'] ?? null,
            $defaultFrom,
            $defaultTo,
        );

        $result = app(TrendsAnalyticsService::class)->getTrendsData(
            (int) ($this->filters['days'] ?? 7),
            (string) ($this->filters['type'] ?? ''),
            (string) ($this->filters['genre'] ?? ''),
            (int) ($this->filters['year_from'] ?: 0),
            (int) ($this->filters['year_to'] ?: 0),
            $fromDate,
            $toDate,
        );

        $this->filters['days'] = $result['period']['days'];
        $this->filters['from'] = $result['period']['from'];
        $this->filters['to'] = $result['period']['to'];
        $this->filters['type'] = $result['filters']['type'];
        $this->filters['genre'] = $result['filters']['genre'];
        $this->filters['year_from'] = (string) ($result['filters']['year_from'] ?: '');
        $this->filters['year_to'] = (string) ($result['filters']['year_to'] ?: '');

        $this->items = $result['items']->map(static function ($item): array {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'poster_url' => $item->poster_url,
                'year' => $item->year,
                'type' => $item->type,
                'imdb_rating' => $item->imdb_rating,
                'imdb_votes' => $item->imdb_votes,
                'clicks' => $item->clicks,
            ];
        })->all();

        $this->form->fill($this->filters);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('filters')
            ->extraAttributes([
                'class' => 'grid gap-4 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6',
            ])
            ->schema([
                Select::make('days')
                    ->label(__('admin.trends.filters.days'))
                    ->options(
                        collect($this->dayOptions)
                            ->mapWithKeys(fn (int $days): array => [$days => __('admin.trends.days_option', ['days' => $days])])
                            ->all()
                    )
                    ->afterStateUpdated(function (?string $state): void {
                        $days = (int) ($state ?? 7);
                        $this->filters['days'] = $days;
                        $defaultTo = CarbonImmutable::now();
                        $defaultFrom = $defaultTo->subDays($days);

                        [$from, $to] = AnalyticsFilters::parseDateRange(null, null, $defaultFrom, $defaultTo);

                        $this->filters['from'] = $from->format('Y-m-d');
                        $this->filters['to'] = $to->format('Y-m-d');
                        $this->form->fill($this->filters);
                        $this->refreshData();
                    }),
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
                Select::make('type')
                    ->label(__('admin.trends.filters.type'))
                    ->options(fn (): array => $this->typeOptions)
                    ->searchable()
                    ->afterStateUpdated(function (?string $state): void {
                        $this->filters['type'] = $state ?? '';
                        $this->refreshData();
                    }),
                TextInput::make('genre')
                    ->label(__('admin.trends.filters.genre'))
                    ->placeholder(__('admin.trends.genre_placeholder'))
                    ->visible(fn (): bool => $this->showAdvancedFilters)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state): void {
                        $this->filters['genre'] = $state ?? '';
                        $this->refreshData();
                    }),
                TextInput::make('year_from')
                    ->label(__('admin.trends.filters.year_from'))
                    ->placeholder(__('admin.trends.year_from_placeholder'))
                    ->visible(fn (): bool => $this->showAdvancedFilters)
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state): void {
                        $this->filters['year_from'] = $state ?? '';
                        $this->refreshData();
                    }),
                TextInput::make('year_to')
                    ->label(__('admin.trends.filters.year_to'))
                    ->placeholder(__('admin.trends.year_to_placeholder'))
                    ->visible(fn (): bool => $this->showAdvancedFilters)
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state): void {
                        $this->filters['year_to'] = $state ?? '';
                        $this->refreshData();
                    }),
                Actions::make([
                    Action::make('refresh')
                        ->label(__('admin.trends.apply'))
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
