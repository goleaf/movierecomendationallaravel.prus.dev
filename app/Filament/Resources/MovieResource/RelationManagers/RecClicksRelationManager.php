<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovieResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecClicksRelationManager extends RelationManager
{
    protected static string $relationship = 'recClicks';

    protected static ?string $title = 'Clicks';

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
                TextColumn::make('variant')
                    ->badge()
                    ->sortable(),
                TextColumn::make('source')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Clicked at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
