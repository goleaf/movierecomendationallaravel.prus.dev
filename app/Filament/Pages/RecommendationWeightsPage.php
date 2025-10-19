<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\RecommendationWeightsSettings;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;

final class RecommendationWeightsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $slug = 'recommendation-weights';

    protected static string $view = 'filament.recommendation-weights.page';

    /** @var array{A: array<string, string>, B: array<string, string>, ab_split: array<string, string>, seed: ?string} */
    public array $data = [
        'A' => ['pop' => '0.00', 'recent' => '0.00', 'pref' => '0.00'],
        'B' => ['pop' => '0.00', 'recent' => '0.00', 'pref' => '0.00'],
        'ab_split' => ['A' => '50.00', 'B' => '50.00'],
        'seed' => null,
    ];

    /** @var array<string, array<string, float>> */
    public array $normalised = [
        'A' => ['pop' => 0.0, 'recent' => 0.0, 'pref' => 0.0],
        'B' => ['pop' => 0.0, 'recent' => 0.0, 'pref' => 0.0],
    ];

    private ?SettingsRepository $settingsRepository = null;

    public static function getNavigationLabel(): string
    {
        return (string) __('admin.recommendation_weights.navigation');
    }

    public function getTitle(): string
    {
        return (string) __('admin.recommendation_weights.title');
    }

    public function boot(SettingsRepository $settingsRepository): void
    {
        $this->settingsRepository = $settingsRepository;
    }

    public function mount(): void
    {
        $this->fillFormFromSettings();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('admin.recommendation_weights.sections.variant_a'))
                    ->schema($this->variantFields('A')),
                Section::make(__('admin.recommendation_weights.sections.variant_b'))
                    ->schema($this->variantFields('B')),
                Section::make(__('admin.recommendation_weights.sections.ab_split'))
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('ab_split.A')
                                    ->label(__('admin.recommendation_weights.fields.ab_split_a'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.1')
                                    ->suffix('%')
                                    ->required(),
                                TextInput::make('ab_split.B')
                                    ->label(__('admin.recommendation_weights.fields.ab_split_b'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.1')
                                    ->suffix('%')
                                    ->required(),
                            ])
                            ->columns(2),
                        TextInput::make('seed')
                            ->label(__('admin.recommendation_weights.fields.seed'))
                            ->helperText(__('admin.recommendation_weights.fields.seed_helper'))
                            ->placeholder(__('admin.recommendation_weights.fields.seed_placeholder')),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $payload = RecommendationWeightsSettings::store($this->repository(), [
            'A' => $this->toNumericVariant($state['A'] ?? []),
            'B' => $this->toNumericVariant($state['B'] ?? []),
            'ab_split' => [
                'A' => (float) ($state['ab_split']['A'] ?? 0.0),
                'B' => (float) ($state['ab_split']['B'] ?? 0.0),
            ],
            'seed' => $state['seed'] ?? null,
        ]);

        $this->data = [
            'A' => $this->formatVariant($payload['A']),
            'B' => $this->formatVariant($payload['B']),
            'ab_split' => [
                'A' => $this->formatNumber($payload['ab_split']['A']),
                'B' => $this->formatNumber($payload['ab_split']['B']),
            ],
            'seed' => $payload['seed'],
        ];

        $this->form->fill($this->data);

        $defaults = RecommendationWeightsSettings::defaults();
        $this->normalised = [
            'A' => $this->normalise($payload['A'], $defaults['A']),
            'B' => $this->normalise($payload['B'], $defaults['B']),
        ];

        Notification::make()
            ->title(__('admin.recommendation_weights.actions.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<int, TextInput>
     */
    private function variantFields(string $variant): array
    {
        return [
            TextInput::make("{$variant}.pop")
                ->label(__('admin.recommendation_weights.fields.pop'))
                ->numeric()
                ->minValue(0)
                ->step('0.1')
                ->required(),
            TextInput::make("{$variant}.recent")
                ->label(__('admin.recommendation_weights.fields.recent'))
                ->numeric()
                ->minValue(0)
                ->step('0.1')
                ->required(),
            TextInput::make("{$variant}.pref")
                ->label(__('admin.recommendation_weights.fields.pref'))
                ->numeric()
                ->minValue(0)
                ->step('0.1')
                ->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $weights
     * @return array<string, float>
     */
    private function toNumericVariant(array $weights): array
    {
        return [
            'pop' => (float) ($weights['pop'] ?? 0.0),
            'recent' => (float) ($weights['recent'] ?? 0.0),
            'pref' => (float) ($weights['pref'] ?? 0.0),
        ];
    }

    /**
     * @param  array<string, float>  $weights
     * @return array<string, string>
     */
    private function formatVariant(array $weights): array
    {
        return [
            'pop' => $this->formatNumber($weights['pop'] ?? 0.0),
            'recent' => $this->formatNumber($weights['recent'] ?? 0.0),
            'pref' => $this->formatNumber($weights['pref'] ?? 0.0),
        ];
    }

    private function formatNumber(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param  array<string, float>  $weights
     * @param  array<string, float>  $fallback
     * @return array<string, float>
     */
    private function normalise(array $weights, array $fallback): array
    {
        $values = [
            'pop' => max(0.0, (float) ($weights['pop'] ?? 0.0)),
            'recent' => max(0.0, (float) ($weights['recent'] ?? 0.0)),
            'pref' => max(0.0, (float) ($weights['pref'] ?? 0.0)),
        ];

        $total = array_sum($values);

        if ($total <= 0.0) {
            $values = [
                'pop' => max(0.0, $fallback['pop']),
                'recent' => max(0.0, $fallback['recent']),
                'pref' => max(0.0, $fallback['pref']),
            ];
            $total = array_sum($values);
        }

        if ($total <= 0.0) {
            return [
                'pop' => 0.3333,
                'recent' => 0.3333,
                'pref' => 0.3334,
            ];
        }

        return array_map(
            static fn (float $value): float => round($value / $total, 4),
            $values,
        );
    }

    private function fillFormFromSettings(): void
    {
        $settings = RecommendationWeightsSettings::fromRepository($this->repository());
        $defaults = RecommendationWeightsSettings::defaults();

        $this->data = [
            'A' => $this->formatVariant($settings->A),
            'B' => $this->formatVariant($settings->B),
            'ab_split' => [
                'A' => $this->formatNumber($settings->ab_split['A'] ?? 0.0),
                'B' => $this->formatNumber($settings->ab_split['B'] ?? 0.0),
            ],
            'seed' => $settings->seed,
        ];

        $this->form->fill($this->data);

        $this->normalised = [
            'A' => $this->normalise($settings->A, $defaults['A']),
            'B' => $this->normalise($settings->B, $defaults['B']),
        ];
    }

    private function repository(): SettingsRepository
    {
        return $this->settingsRepository ??= app(SettingsRepository::class);
    }
}
