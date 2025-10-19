<?php

namespace App\Filament\Resources\MovieResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecAbLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'recAbLogs';

    protected static ?string $title = 'A/B Impressions';

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
                TextColumn::make('meta')
                    ->formatStateUsing(function (?array $state): ?string {
                        if (empty($state)) {
                            return null;
                        }

                        return json_encode($state, JSON_UNESCAPED_UNICODE);
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Logged at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
