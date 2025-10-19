<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceHistoryResource\Pages;
use App\Models\DeviceHistory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use TomatoPHP\FilamentBookmarksMenu\Filament\Tables\BookmarkAction as BookmarkTableAction;
use TomatoPHP\FilamentBookmarksMenu\Filament\Tables\BookmarkBulkAction;
use TomatoPHP\FilamentBookmarksMenu\Filament\Tables\BookmarkBulkClearAction;

class DeviceHistoryResource extends Resource
{
    protected static ?string $model = DeviceHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Telemetry';

    protected static ?string $modelLabel = 'Device View';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('viewed_at')
                    ->label('Viewed at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('movie.title')
                    ->label('Movie')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('placement')
                    ->badge()
                    ->sortable(),
                TextColumn::make('device_id')
                    ->label('Device')
                    ->searchable()
                    ->copyable()
                    ->limit(24),
                TextColumn::make('created_at')
                    ->label('Recorded at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('viewed_at', 'desc')
            ->filters([
                Filter::make('viewed_at')
                    ->form([
                        DatePicker::make('from')->label('From')->native(false),
                        DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('viewed_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('viewed_at', '<=', $date));
                    }),
                SelectFilter::make('placement')
                    ->options(fn () => DeviceHistory::query()
                        ->select('placement')
                        ->whereNotNull('placement')
                        ->distinct()
                        ->orderBy('placement')
                        ->pluck('placement', 'placement')
                        ->all()),
                Filter::make('device_id')
                    ->form([
                        TextInput::make('device')->label('Device ID'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $device = $data['device'] ?? null;

                        if ($device === null || $device === '') {
                            return $query;
                        }

                        return $query->where('device_id', 'like', "%{$device}%");
                    }),
                SelectFilter::make('movie_id')
                    ->relationship('movie', 'title')
                    ->label('Movie'),
            ])
            ->actions([
                BookmarkTableAction::make()->page('view'),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BookmarkBulkAction::make()->page('view'),
                    BookmarkBulkClearAction::make()->page('view'),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('viewed_at')
                    ->label('Viewed at')
                    ->dateTime(),
                TextEntry::make('movie.title')
                    ->label('Movie'),
                TextEntry::make('placement')->badge(),
                TextEntry::make('device_id')
                    ->label('Device'),
                TextEntry::make('created_at')
                    ->label('Recorded at')
                    ->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceHistories::route('/'),
            'view' => Pages\ViewDeviceHistory::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['device_id', 'placement'];
    }
}
