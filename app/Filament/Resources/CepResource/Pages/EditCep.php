<?php

namespace App\Filament\Resources\CepResource\Pages;

use App\Filament\Resources\CepResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCep extends EditRecord
{
    protected static string $resource = CepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
