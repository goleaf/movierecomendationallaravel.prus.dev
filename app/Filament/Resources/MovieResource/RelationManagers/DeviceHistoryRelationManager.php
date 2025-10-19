<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovieResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeviceHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'deviceHistory';

    protected static ?string $title = 'Device Views';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->heading(static::$title)
            ->columns([
                TextColumn::make('device_id')
                    ->label('Device')
                    ->searchable()
                    ->copyable()
                    ->limit(24),
                TextColumn::make('placement')
                    ->badge()
                    ->sortable(),
                TextColumn::make('viewed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Recorded at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('viewed_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
