<?php

namespace App\Filament\Resources\CepResource\Pages;

use App\Filament\Resources\CepResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCep extends ViewRecord
{
    protected static string $resource = CepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
