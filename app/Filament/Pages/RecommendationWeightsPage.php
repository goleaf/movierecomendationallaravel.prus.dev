<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\RecommendationWeightsSettings;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

final class RecommendationWeightsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static ?string $navigationLabel = 'Recommendation weights';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $slug = 'recommendation-weights';

    protected static string $view = 'filament.recommendations.weights';

    /**
     * @var array{
     *     variant_a?: array{pop?: float|int|null, recent?: float|int|null, pref?: float|int|null},
     *     variant_b?: array{pop?: float|int|null, recent?: float|int|null, pref?: float|int|null},
     *     ab_split?: array{A?: float|int|null, B?: float|int|null},
     *     seed?: ?string,
     * }
     */
    public array $data = [];

    public function mount(): void
    {
        $settings = app(RecommendationWeightsSettings::class);

        $this->form->fill([
            'variant_a' => $settings->variant_a,
            'variant_b' => $settings->variant_b,
            'ab_split' => $settings->ab_split,
            'seed' => $settings->seed,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Variant A weights')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                $this->weightInput('variant_a.pop', 'Popularity'),
                                $this->weightInput('variant_a.recent', 'Recency'),
                                $this->weightInput('variant_a.pref', 'Preference'),
                            ]),
                    ]),
                Section::make('Variant B weights')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                $this->weightInput('variant_b.pop', 'Popularity'),
                                $this->weightInput('variant_b.recent', 'Recency'),
                                $this->weightInput('variant_b.pref', 'Preference'),
                            ]),
                    ]),
                Fieldset::make('Experiment split')
                    ->schema([
                        TextInput::make('ab_split.A')
                            ->label('Variant A weight')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->required(),
                        TextInput::make('ab_split.B')
                            ->label('Variant B weight')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Experiment seed')
                    ->schema([
                        TextInput::make('seed')
                            ->label('Seed (optional)')
                            ->maxLength(255)
                            ->nullable(),
                    ]),
            ]);
    }

    public function submit(): void
    {
        $validated = $this->form->validate();
        $data = $validated['data'] ?? [];

        /** @var RecommendationWeightsSettings $settings */
        $settings = app(RecommendationWeightsSettings::class);
        $settings->variant_a = $this->castWeights($data['variant_a'] ?? []);
        $settings->variant_b = $this->castWeights($data['variant_b'] ?? []);
        $settings->ab_split = $this->castSplit($data['ab_split'] ?? []);
        $settings->seed = isset($data['seed']) && is_string($data['seed']) && $data['seed'] !== ''
            ? $data['seed']
            : null;
        $settings->save();

        $this->form->fill([
            'variant_a' => $settings->variant_a,
            'variant_b' => $settings->variant_b,
            'ab_split' => $settings->ab_split,
            'seed' => $settings->seed,
        ]);

        Notification::make()
            ->title('Recommendation weights updated')
            ->success()
            ->body('Updated variant weights and split preferences are now active.')
            ->send();
    }

    private function weightInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->maxValue(1)
            ->required();
    }

    /**
     * @param  array<string, float|int|null>  $weights
     * @return array{pop: float, recent: float, pref: float}
     */
    private function castWeights(array $weights): array
    {
        return [
            'pop' => (float) ($weights['pop'] ?? 0.0),
            'recent' => (float) ($weights['recent'] ?? 0.0),
            'pref' => (float) ($weights['pref'] ?? 0.0),
        ];
    }

    /**
     * @param  array<string, float|int|null>  $split
     * @return array{A: float, B: float}
     */
    private function castSplit(array $split): array
    {
        return [
            'A' => (float) ($split['A'] ?? 0.0),
            'B' => (float) ($split['B'] ?? 0.0),
        ];
    }
}
