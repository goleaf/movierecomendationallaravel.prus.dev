<?php

declare(strict_types=1);

namespace App\Filament\Pages\Administration;

use App\Settings\RecommendationWeightsSettings;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Actions as ActionsComponent;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;

final class RecommendationWeightsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static ?string $navigationLabel = 'Recommendation weights';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $slug = 'recommendation-weights';

    protected static string $view = 'filament.administration.recommendation-weights';

    /** @var array<string, float> */
    public array $formData = [];

    public function mount(): void
    {
        $settings = app(RecommendationWeightsSettings::class);

        $this->formData = [
            'variant_a_pop' => $settings->variant_a_pop,
            'variant_a_recent' => $settings->variant_a_recent,
            'variant_a_pref' => $settings->variant_a_pref,
            'variant_b_pop' => $settings->variant_b_pop,
            'variant_b_recent' => $settings->variant_b_recent,
            'variant_b_pref' => $settings->variant_b_pref,
            'ab_split_a' => $settings->ab_split_a,
            'ab_split_b' => $settings->ab_split_b,
        ];

        $this->form->fill($this->formData);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('formData')
            ->schema([
                Section::make('Variant A weights')
                    ->schema([
                        Grid::make()
                            ->schema([
                                $this->weightInput('variant_a_pop', 'Popularity'),
                                $this->weightInput('variant_a_recent', 'Recency'),
                                $this->weightInput('variant_a_pref', 'Preferences'),
                            ]),
                    ])->columns(1),
                Section::make('Variant B weights')
                    ->schema([
                        Grid::make()
                            ->schema([
                                $this->weightInput('variant_b_pop', 'Popularity'),
                                $this->weightInput('variant_b_recent', 'Recency'),
                                $this->weightInput('variant_b_pref', 'Preferences'),
                            ]),
                    ])->columns(1),
                Section::make('A/B split')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('ab_split_a')
                                    ->label('Variant A audience %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required(),
                                TextInput::make('ab_split_b')
                                    ->label('Variant B audience %')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required(),
                            ]),
                    ])->columns(1),
                ActionsComponent::make([
                    FormAction::make('save')
                        ->label('Save weights')
                        ->color('primary')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ])->columnSpanFull()->alignEnd(),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $validator = $this->validator($state);
        $validator->validate();

        /** @var array<string, float> $validated */
        $validated = $validator->validated();

        $settings = app(RecommendationWeightsSettings::class);
        $settings->variant_a_pop = (float) $validated['variant_a_pop'];
        $settings->variant_a_recent = (float) $validated['variant_a_recent'];
        $settings->variant_a_pref = (float) $validated['variant_a_pref'];
        $settings->variant_b_pop = (float) $validated['variant_b_pop'];
        $settings->variant_b_recent = (float) $validated['variant_b_recent'];
        $settings->variant_b_pref = (float) $validated['variant_b_pref'];
        $settings->ab_split_a = (float) $validated['ab_split_a'];
        $settings->ab_split_b = (float) $validated['ab_split_b'];
        $settings->save();

        app()->forgetInstance(RecommendationWeightsSettings::class);

        $this->formData = $validated;
        $this->form->fill($this->formData);

        Notification::make()
            ->success()
            ->title('Recommendation weights updated successfully.')
            ->send();
    }

    protected function weightInput(string $field, string $label): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->maxValue(1)
            ->step(0.01)
            ->required();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function validator(array $state): ValidatorContract
    {
        return Validator::make($state, [
            'variant_a_pop' => ['required', 'numeric', 'between:0,1'],
            'variant_a_recent' => ['required', 'numeric', 'between:0,1'],
            'variant_a_pref' => ['required', 'numeric', 'between:0,1'],
            'variant_b_pop' => ['required', 'numeric', 'between:0,1'],
            'variant_b_recent' => ['required', 'numeric', 'between:0,1'],
            'variant_b_pref' => ['required', 'numeric', 'between:0,1'],
            'ab_split_a' => ['required', 'numeric', 'min:0'],
            'ab_split_b' => ['required', 'numeric', 'min:0'],
        ])->after(function (ValidatorContract $validator) use ($state): void {
            $this->assertTripletSumsToOne($validator, $state, 'variant_a');
            $this->assertTripletSumsToOne($validator, $state, 'variant_b');

            $abSplitTotal = (float) ($state['ab_split_a'] ?? 0) + (float) ($state['ab_split_b'] ?? 0);
            if ($abSplitTotal <= 0.0) {
                $validator->errors()->add('ab_split_a', 'The A/B split must allocate traffic to at least one variant.');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function assertTripletSumsToOne(ValidatorContract $validator, array $state, string $prefix): void
    {
        $sum = (float) ($state[sprintf('%s_pop', $prefix)] ?? 0)
            + (float) ($state[sprintf('%s_recent', $prefix)] ?? 0)
            + (float) ($state[sprintf('%s_pref', $prefix)] ?? 0);

        if (abs($sum - 1.0) > 0.01) {
            $validator->errors()->add(sprintf('%s_pop', $prefix), 'Weights must total 1.0 for each variant.');
        }
    }
}
