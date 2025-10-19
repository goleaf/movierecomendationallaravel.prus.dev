<?php

namespace App\Filament\Resources\CepResource\Pages;

use App\Filament\Resources\CepResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCeps extends ListRecords
{
    protected static string $resource = CepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
