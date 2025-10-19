<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\RecClickResource\Pages;
use App\Models\RecClick;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * @extends Resource<RecClick>
 */
class RecClickResource extends Resource
{
    protected static ?string $model = RecClick::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cursor-arrow-rays';

    protected static UnitEnum|string|null $navigationGroup = 'Telemetry';

    protected static ?string $modelLabel = 'Recommendation Click';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Clicked at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('movie.title')
                    ->label('Movie')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('placement')
                    ->badge()
                    ->sortable(),
                TextColumn::make('variant')
                    ->badge()
                    ->sortable(),
                TextColumn::make('device_id')
                    ->label('Device')
                    ->searchable()
                    ->copyable()
                    ->limit(24),
                TextColumn::make('source')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From')->native(false),
                        DatePicker::make('until')->label('Until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date));
                    }),
                SelectFilter::make('variant')
                    ->options(fn () => RecClick::query()
                        ->select('variant')
                        ->distinct()
                        ->orderBy('variant')
                        ->pluck('variant', 'variant')
                        ->all()),
                SelectFilter::make('placement')
                    ->options(fn () => RecClick::query()
                        ->select('placement')
                        ->distinct()
                        ->orderBy('placement')
                        ->pluck('placement', 'placement')
                        ->all()),
                SelectFilter::make('source')
                    ->options(fn () => RecClick::query()
                        ->select('source')
                        ->whereNotNull('source')
                        ->distinct()
                        ->orderBy('source')
                        ->pluck('source', 'source')
                        ->all()),
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('created_at')
                    ->label('Clicked at')
                    ->dateTime(),
                TextEntry::make('movie.title')
                    ->label('Movie'),
                TextEntry::make('placement')->badge(),
                TextEntry::make('variant')->badge(),
                TextEntry::make('device_id')
                    ->label('Device'),
                TextEntry::make('source')
                    ->label('Source'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecClicks::route('/'),
            'view' => Pages\ViewRecClick::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['device_id', 'placement', 'variant', 'source'];
    }
}
