<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MovieResource\Pages;
use App\Filament\Resources\MovieResource\RelationManagers\DeviceHistoryRelationManager;
use App\Filament\Resources\MovieResource\RelationManagers\RecAbLogsRelationManager;
use App\Filament\Resources\MovieResource\RelationManagers\RecClicksRelationManager;
use App\Models\Movie;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use TomatoPHP\FilamentTranslationComponent\Components\Translation;

class MovieResource extends Resource
{
    protected static ?string $model = Movie::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Details')
                    ->schema([
                        TextInput::make('imdb_tt')
                            ->label('IMDb ID')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(16),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('plot')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('type')
                            ->required()
                            ->maxLength(32),
                        TextInput::make('year')
                            ->numeric()
                            ->minValue(1870)
                            ->maxValue(3000),
                        DatePicker::make('release_date')
                            ->native(false),
                        TextInput::make('imdb_rating')
                            ->numeric()
                            ->step('0.1')
                            ->minValue(0)
                            ->maxValue(10),
                        TextInput::make('imdb_votes')
                            ->numeric()
                            ->minValue(0)
                            ->step(1),
                        TextInput::make('runtime_min')
                            ->numeric()
                            ->minValue(0)
                            ->step(1),
                        TagsInput::make('genres')
                            ->placeholder('Add genres...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Media')
                    ->schema([
                        TextInput::make('poster_url')
                            ->label('Poster URL')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('backdrop_url')
                            ->label('Backdrop URL')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Translations')
                    ->schema([
                        Translation::make('translations.title')
                            ->label('Title Translations')
                            ->default([])
                            ->columnSpanFull(),
                        Translation::make('translations.plot')
                            ->label('Plot Translations')
                            ->default([])
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Section::make('Raw Payload')
                    ->schema([
                        Textarea::make('raw')
                            ->rows(8)
                            ->columnSpanFull()
                            ->helperText('Stored JSON payload from the ingestion service.')
                            ->rule('nullable|json')
                            ->dehydrateStateUsing(function (?string $state): ?array {
                                if ($state === null) {
                                    return null;
                                }

                                $trimmed = trim($state);
                                if ($trimmed === '') {
                                    return null;
                                }

                                $decoded = json_decode($trimmed, true);

                                return $decoded === null ? null : $decoded;
                            })
                            ->afterStateHydrated(function (Textarea $component, $state): void {
                                if (is_array($state)) {
                                    $component->state(
                                        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                    );
                                }
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('imdb_tt')
                    ->label('IMDb ID')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('year')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('release_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('imdb_rating')
                    ->sortable()
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 1)),
                TextColumn::make('imdb_votes')
                    ->sortable()
                    ->numeric(),
                TextColumn::make('weighted_score')
                    ->sortable()
                    ->formatStateUsing(fn (?float $state): ?string => $state === null ? null : number_format($state, 2)),
                TextColumn::make('genres')
                    ->label('Genres')
                    ->formatStateUsing(function (?array $state): ?string {
                        if ($state === null) {
                            return null;
                        }

                        return implode(', ', $state);
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
            ])
            ->defaultSort('imdb_votes', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'movie' => 'Movie',
                        'series' => 'Series',
                        'mini-series' => 'Mini-series',
                        'documentary' => 'Documentary',
                    ]),
                Filter::make('release_date')
                    ->form([
                        DatePicker::make('from')->label('From')->native(false),
                        DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('release_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('release_date', '<=', $date));
                    }),
            ])
            ->actions([
                BookmarkTableAction::make()->page('view'),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BookmarkBulkAction::make()->page('view'),
                    BookmarkBulkClearAction::make()->page('view'),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RecAbLogsRelationManager::class,
            RecClicksRelationManager::class,
            DeviceHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMovies::route('/'),
            'create' => Pages\CreateMovie::route('/create'),
            'view' => Pages\ViewMovie::route('/{record}'),
            'edit' => Pages\EditMovie::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'imdb_tt'];
    }
}
