<?php

declare(strict_types=1);

namespace App\Filament\Resources\InquiryResource\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class RepliesRelationManager extends RelationManager
{
    protected static string $relationship = 'replies';

    public function form(Form $form): Form
    {
        return $form->schema([
            Textarea::make('message')
                ->required()
                ->rows(4)
                ->maxLength(5000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Responder')
                    ->default('System')
                    ->wrap(),
                TextColumn::make('message')
                    ->label('Reply')
                    ->wrap()
                    ->formatStateUsing(static fn (?string $state): ?string => $state ? Str::limit($state, 80) : null)
                    ->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('created_at')
                    ->label('Sent at')
                    ->since(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        $data['ip_address'] = request()?->ip();
                        $data['user_agent'] = request()?->userAgent();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
