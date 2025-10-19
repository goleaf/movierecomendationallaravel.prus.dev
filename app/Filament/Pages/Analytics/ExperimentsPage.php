<?php

declare(strict_types=1);

namespace App\Filament\Pages\Analytics;

use App\Services\RecommendationSnapshotService;
use App\Settings\RecommendationWeightsSettings;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Jacobtims\InlineDateTimePicker\Forms\Components\InlineDateTimePicker;

final class ExperimentsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $slug = 'experiments';

    protected static string $view = 'filament.analytics.experiments';

    /** @var array<string, float> */
    public array $weights = [
        'pop' => 0.0,
        'recent' => 0.0,
        'pref' => 0.0,
    ];

    /** @var array{from: string, to: string} */
    public array $filters = [
        'from' => '',
        'to' => '',
    ];

    public ?string $contributionSvg = null;

    public ?string $weightSvg = null;

    /** @var array<int, array<string, mixed>> */
    public array $dailyRows = [];

    public float $weightSum = 0.0;

    public static function getNavigationLabel(): string
    {
        return __('admin.analytics_tabs.experiments.label');
    }

    public function getTitle(): string
    {
        return __('admin.experiments.title');
    }

    public function mount(): void
    {
        $settings = app(RecommendationWeightsSettings::class);
        $weights = $settings->weightsForVariant('B');
        $this->weights = [
            'pop' => round($weights['pop'], 4),
            'recent' => round($weights['recent'], 4),
            'pref' => round($weights['pref'], 4),
        ];
        $this->filters = [
            'from' => now()->subDays(14)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ];

        $this->updateWeightSum();

        $this->form->fill([
            'weights' => $this->weights,
            'filters' => $this->filters,
        ]);

        $this->refreshSnapshots();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('admin.experiments.weights.heading'))
                    ->description(__('admin.experiments.weights.description'))
                    ->schema([
                        Group::make()
                            ->statePath('weights')
                            ->schema([
                                TextInput::make('pop')
                                    ->label(__('admin.experiments.weights.fields.pop'))
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->required()
                                    ->afterStateUpdated(function (): void {
                                        $this->weights = $this->form->getState()['weights'] ?? $this->weights;
                                        $this->updateWeightSum();
                                    }),
                                TextInput::make('recent')
                                    ->label(__('admin.experiments.weights.fields.recent'))
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->required()
                                    ->afterStateUpdated(function (): void {
                                        $this->weights = $this->form->getState()['weights'] ?? $this->weights;
                                        $this->updateWeightSum();
                                    }),
                                TextInput::make('pref')
                                    ->label(__('admin.experiments.weights.fields.pref'))
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->maxValue(1)
                                    ->required()
                                    ->afterStateUpdated(function (): void {
                                        $this->weights = $this->form->getState()['weights'] ?? $this->weights;
                                        $this->updateWeightSum();
                                    }),
                            ])
                            ->columns(3),
                        Placeholder::make('weight_sum')
                            ->label(__('admin.experiments.weights.sum_label'))
                            ->content(fn (): string => number_format($this->weightSum, 2))
                            ->columnSpan(3),
                        Actions::make([
                            Actions\Action::make('save')
                                ->label(__('admin.experiments.weights.save'))
                                ->color('primary')
                                ->action(fn () => $this->saveWeights()),
                        ])->columnSpanFull(),
                    ]),
                Section::make(__('admin.experiments.filters.heading'))
                    ->schema([
                        Group::make()
                            ->statePath('filters')
                            ->schema([
                                InlineDateTimePicker::make('from')
                                    ->label(__('admin.experiments.filters.from'))
                                    ->time(false)
                                    ->seconds(false)
                                    ->afterStateUpdated(function (?string $state): void {
                                        $this->filters['from'] = $state ?? '';
                                        $this->refreshSnapshots();
                                    }),
                                InlineDateTimePicker::make('to')
                                    ->label(__('admin.experiments.filters.to'))
                                    ->time(false)
                                    ->seconds(false)
                                    ->afterStateUpdated(function (?string $state): void {
                                        $this->filters['to'] = $state ?? '';
                                        $this->refreshSnapshots();
                                    }),
                        ])->columns(2),
                    ]),
            ]);
    }

    public function saveWeights(): void
    {
        $state = $this->form->getState();
        $weights = $state['weights'] ?? $this->weights;

        $pop = max(0.0, (float) ($weights['pop'] ?? 0.0));
        $recent = max(0.0, (float) ($weights['recent'] ?? 0.0));
        $pref = max(0.0, (float) ($weights['pref'] ?? 0.0));
        $total = $pop + $recent + $pref;

        if ($total <= 0.0) {
            Notification::make()
                ->title(__('admin.experiments.notifications.invalid'))
                ->danger()
                ->send();

            return;
        }

        $normalized = [
            'pop' => $pop / $total,
            'recent' => $recent / $total,
            'pref' => $pref / $total,
        ];

        $settings = app(RecommendationWeightsSettings::class);
        $settings->variant_b_pop = $normalized['pop'];
        $settings->variant_b_recent = $normalized['recent'];
        $settings->variant_b_pref = $normalized['pref'];
        $settings->save();

        $this->weights = [
            'pop' => round($normalized['pop'], 4),
            'recent' => round($normalized['recent'], 4),
            'pref' => round($normalized['pref'], 4),
        ];
        $this->updateWeightSum();
        $this->form->fill([
            'weights' => $this->weights,
            'filters' => $this->filters,
        ]);

        Notification::make()
            ->title(__('admin.experiments.notifications.saved'))
            ->success()
            ->send();
    }

    public function refreshSnapshots(): void
    {
        $from = $this->parseDate($this->filters['from'] ?? now()->subDays(14)->format('Y-m-d'));
        $to = $this->parseDate($this->filters['to'] ?? now()->format('Y-m-d'));

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $service = app(RecommendationSnapshotService::class);
        $data = $service->dailySeries('B', $from, $to);
        $this->dailyRows = $data['rows'];
        $this->contributionSvg = $service->buildContributionSvg($data);
        $this->weightSvg = $service->buildWeightSvg($data);

        $this->filters['from'] = $from->format('Y-m-d');
        $this->filters['to'] = $to->format('Y-m-d');
        $this->form->fill([
            'weights' => $this->weights,
            'filters' => $this->filters,
        ]);
    }

    public function updateWeightSum(): void
    {
        $this->weightSum = round(
            (float) $this->weights['pop']
            + (float) $this->weights['recent']
            + (float) $this->weights['pref'],
            4
        );
    }

    private function parseDate(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return CarbonImmutable::now();
        }
    }
}
