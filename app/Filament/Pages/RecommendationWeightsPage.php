<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\RecommendationWeightsSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use InvalidArgumentException;

/**
 * @property Form $form
 */
final class RecommendationWeightsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Recommendation Weights';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $slug = 'recommendation-weights';

    protected static ?string $title = 'Recommendation Weights';

    protected static string $view = 'filament.pages.recommendation-weights';

    /** @var array{split: array{A: float, B: float}, variants: array{A: array<string, float>, B: array<string, float>}} */
    public array $data = [
        'split' => [
            'A' => 50.0,
            'B' => 50.0,
        ],
        'variants' => [
            'A' => [
                'pop' => 0.55,
                'recent' => 0.20,
                'pref' => 0.25,
            ],
            'B' => [
                'pop' => 0.35,
                'recent' => 0.15,
                'pref' => 0.50,
            ],
        ],
    ];

    public function mount(): void
    {
        $settings = app(RecommendationWeightsSettings::class);

        $this->data = [
            'split' => $settings->split,
            'variants' => [
                'A' => $settings->variant_a,
                'B' => $settings->variant_b,
            ],
        ];

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('A/B Experiment Split')
                    ->description('Adjust the percentage split between variants A and B. Values will be normalised to total 100%.')
                    ->schema([
                        TextInput::make('split.A')
                            ->label('Variant A (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                        TextInput::make('split.B')
                            ->label('Variant B (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Variant A Weights')
                    ->description('Internal weights are normalised to 1.0 on save to keep the scoring distribution balanced.')
                    ->schema([
                        TextInput::make('variants.A.pop')
                            ->label('Popularity weight')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('variants.A.recent')
                            ->label('Recently added weight')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('variants.A.pref')
                            ->label('Preference weight')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('Variant B Weights')
                    ->description('Internal weights are normalised to 1.0 on save to keep the scoring distribution balanced.')
                    ->schema([
                        TextInput::make('variants.B.pop')
                            ->label('Popularity weight')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('variants.B.recent')
                            ->label('Recently added weight')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('variants.B.pref')
                            ->label('Preference weight')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(3),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            $split = $this->normaliseSplit($data['split'] ?? []);
            $variantA = $this->normaliseVariantWeights($data['variants']['A'] ?? [], 'A');
            $variantB = $this->normaliseVariantWeights($data['variants']['B'] ?? [], 'B');

            $settings = app(RecommendationWeightsSettings::class);
            $settings->split = $split;
            $settings->variant_a = $variantA;
            $settings->variant_b = $variantB;
            $settings->save();

            $this->data = [
                'split' => $split,
                'variants' => [
                    'A' => $variantA,
                    'B' => $variantB,
                ],
            ];

            $this->form->fill($this->data);

            Notification::make()
                ->success()
                ->title('Recommendation weights updated')
                ->body('The updated weights will be used for the next round of recommendations.')
                ->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->danger()
                ->title('Unable to save recommendation weights')
                ->body($exception->getMessage())
                ->send();
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title('Unable to save recommendation weights')
                ->body('An unexpected error occurred while saving. Please try again.')
                ->send();
        }
    }

    /**
     * @param array<string, mixed> $split
     * @return array{A: float, B: float}
     */
    private function normaliseSplit(array $split): array
    {
        $variantA = $this->clampPercentage($split['A'] ?? 0);
        $variantB = $this->clampPercentage($split['B'] ?? 0);

        $sum = $variantA + $variantB;

        if ($sum <= 0.0) {
            throw new InvalidArgumentException('Provide at least one positive A/B percentage before saving.');
        }

        if ($sum !== 100.0) {
            $variantA = round(($variantA / $sum) * 100, 2);
            $variantB = round(($variantB / $sum) * 100, 2);
        }

        return [
            'A' => $variantA,
            'B' => $variantB,
        ];
    }

    /**
     * @param array<string, mixed> $weights
     * @return array{pop: float, recent: float, pref: float}
     */
    private function normaliseVariantWeights(array $weights, string $variant): array
    {
        $keys = ['pop', 'recent', 'pref'];
        $sanitised = [];
        $sum = 0.0;

        foreach ($keys as $key) {
            $value = isset($weights[$key]) ? max(0.0, (float) $weights[$key]) : 0.0;
            $sanitised[$key] = $value;
            $sum += $value;
        }

        if ($sum <= 0.0) {
            throw new InvalidArgumentException("Variant {$variant} weights must include at least one positive value.");
        }

        foreach ($sanitised as $key => $value) {
            $sanitised[$key] = round($value / $sum, 4);
        }

        return [
            'pop' => $sanitised['pop'],
            'recent' => $sanitised['recent'],
            'pref' => $sanitised['pref'],
        ];
    }

    private function clampPercentage(float|int|string $value): float
    {
        $numeric = (float) $value;

        if ($numeric < 0.0) {
            $numeric = 0.0;
        }

        if ($numeric > 100.0) {
            $numeric = 100.0;
        }

        return round($numeric, 2);
    }
}
