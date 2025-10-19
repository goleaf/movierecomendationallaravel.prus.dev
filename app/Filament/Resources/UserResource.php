<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
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

/**
 * @extends Resource<User>
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Account')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                            ]),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $context): bool => $context === 'create'),
                    ]),
                Section::make('Address')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                CepInput::make('cep')
                                    ->label('CEP')
                                    ->placeholder('00000-000')
                                    ->formatStateUsing(fn (?string $state): ?string => self::formatCep($state))
                                    ->dehydrateStateUsing(fn (?string $state): ?string => self::stripCepMask($state))
                                    ->setStreetField('street')
                                    ->setNeighborhoodField('neighborhood')
                                    ->setCityField('city')
                                    ->setStateField('state')
                                    ->helperText('Searches Brazilian addresses and fills the fields below automatically.'),
                                TextInput::make('state')
                                    ->label('State')
                                    ->maxLength(2)
                                    ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                    ->reactive()
                                    ->afterStateHydrated(function (TextInput $component, ?string $state): void {
                                        if ($state !== null) {
                                            $component->state(mb_strtoupper($state));
                                        }
                                    })
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        $set('state', $state !== null ? mb_strtoupper($state) : null);
                                    })
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? mb_strtoupper($state) : null),
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cep')
                    ->label('CEP')
                    ->formatStateUsing(fn (?string $state): ?string => self::formatCep($state))
                    ->sortable(),
                TextColumn::make('city')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('state')
                    ->label('UF')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('State')
                    ->options(fn () => User::query()
                        ->whereNotNull('state')
                        ->pluck('state')
                        ->map(fn (?string $state) => $state !== null ? mb_strtoupper($state) : null)
                        ->filter()
                        ->unique()
                        ->sort()
                        ->mapWithKeys(fn (string $state) => [$state => $state])
                        ->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('E-mail'),
                TextEntry::make('cep')
                    ->label('CEP')
                    ->formatStateUsing(fn (?string $state): ?string => self::formatCep($state)),
                TextEntry::make('state')
                    ->label('State')
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? mb_strtoupper($state) : null),
                TextEntry::make('city'),
                TextEntry::make('neighborhood'),
                TextEntry::make('street')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->label('Created'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->label('Updated'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'cep', 'city'];
    }

    private static function formatCep(?string $cep): ?string
    {
        if ($cep === null) {
            return null;
        }

        $trimmed = trim($cep);

        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $trimmed);

        if ($digits === null || strlen($digits) !== 8) {
            return $trimmed;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }

    private static function stripCepMask(?string $cep): ?string
    {
        if ($cep === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $cep);

        if ($digits === null) {
            return null;
        }

        $trimmed = substr($digits, 0, 8);

        return strlen($trimmed) === 8 ? $trimmed : null;
    }
}
