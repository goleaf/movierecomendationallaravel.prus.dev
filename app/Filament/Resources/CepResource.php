<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CepResource\Pages;
use App\Models\Cep;
use App\Support\CepFormatter;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use JeffersonGoncalves\Filament\CepField\Forms\Components\CepInput;

class CepResource extends Resource
{
    protected static ?string $model = Cep::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $recordTitleAttribute = 'cep';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('CEP Lookup')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                CepInput::make('cep')
                                    ->label('CEP')
                                    ->placeholder('00000-000')
                                    ->required()
                                    ->formatStateUsing(fn (?string $state): ?string => CepFormatter::format($state))
                                    ->dehydrateStateUsing(fn (?string $state): ?string => CepFormatter::strip($state))
                                    ->setStreetField('street')
                                    ->setNeighborhoodField('neighborhood')
                                    ->setCityField('city')
                                    ->setStateField('state'),
                                TextInput::make('state')
                                    ->label('State')
                                    ->maxLength(2)
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->reactive()
                                    ->afterStateHydrated(function (TextInput $component, ?string $state): void {
                                        $component->state(CepFormatter::uppercaseState($state));
                                    })
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        $set('state', CepFormatter::uppercaseState($state));
                                    })
                                    ->dehydrateStateUsing(fn (?string $state): ?string => CepFormatter::uppercaseState($state)),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('city')
                                    ->label('City')
                                    ->maxLength(255),
                                TextInput::make('neighborhood')
                                    ->label('Neighborhood')
                                    ->maxLength(255),
                            ]),
                        TextInput::make('street')
                            ->label('Street')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cep')
                    ->label('CEP')
                    ->formatStateUsing(fn (?string $state): ?string => CepFormatter::format($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state')
                    ->label('UF')
                    ->formatStateUsing(fn (?string $state): ?string => CepFormatter::uppercaseState($state))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('neighborhood')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('street')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->label('State')
                    ->options(fn () => Cep::query()
                        ->whereNotNull('state')
                        ->pluck('state')
                        ->map(fn (?string $state) => CepFormatter::uppercaseState($state))
                        ->filter()
                        ->unique()
                        ->values()
                        ->sort()
                        ->mapWithKeys(fn (string $state) => [$state => $state])
                        ->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('cep')
                    ->label('CEP')
                    ->formatStateUsing(fn (?string $state): ?string => CepFormatter::format($state)),
                TextEntry::make('state')
                    ->label('State')
                    ->formatStateUsing(fn (?string $state): ?string => CepFormatter::uppercaseState($state)),
                TextEntry::make('city')
                    ->label('City'),
                TextEntry::make('neighborhood')
                    ->label('Neighborhood'),
                TextEntry::make('street')
                    ->label('Street')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label('Created')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->label('Updated')
                    ->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCeps::route('/'),
            'create' => Pages\CreateCep::route('/create'),
            'view' => Pages\ViewCep::route('/{record}'),
            'edit' => Pages\EditCep::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['cep', 'city', 'neighborhood', 'street', 'state'];
    }
}
