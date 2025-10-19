<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\RecAbLogResource\Pages;
use App\Models\RecAbLog;
use Filament\Forms\Components\DatePicker;
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

final class RecAbLogResource extends Resource
{
    protected static ?string $model = RecAbLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Telemetry';

    protected static ?string $modelLabel = 'A/B Impression';

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Logged at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('placement')
                    ->badge()
                    ->searchable(),
                TextColumn::make('variant')
                    ->badge()
                    ->sortable(),
                TextColumn::make('device_id')
                    ->label('Device')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('movie.title')
                    ->label('Movie')
                    ->searchable(),
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
                            ->when($data['from'] ?? null, static fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, static fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date));
                    }),
                SelectFilter::make('placement')
                    ->options(fn () => RecAbLog::query()
                        ->select('placement')
                        ->distinct()
                        ->orderBy('placement')
                        ->pluck('placement', 'placement')
                        ->all()),
                SelectFilter::make('variant')
                    ->options(fn () => RecAbLog::query()
                        ->select('variant')
                        ->distinct()
                        ->orderBy('variant')
                        ->pluck('variant', 'variant')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('id')->label('ID'),
                TextEntry::make('created_at')
                    ->label('Logged at')
                    ->dateTime(),
                TextEntry::make('placement')->badge(),
                TextEntry::make('variant')->badge(),
                TextEntry::make('device_id')
                    ->label('Device'),
                TextEntry::make('movie.title')
                    ->label('Movie'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecAbLogs::route('/'),
            'view' => Pages\ViewRecAbLog::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['device_id', 'placement', 'variant'];
    }
}
