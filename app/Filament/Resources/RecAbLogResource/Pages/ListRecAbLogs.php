<?php

namespace App\Filament\Resources\RecAbLogResource\Pages;

use App\Filament\Resources\RecAbLogResource;
use Filament\Resources\Pages\ListRecords;
use TomatoPHP\FilamentBookmarksMenu\Filament\Actions\BookmarkAction;

class ListRecAbLogs extends ListRecords
{
    protected static string $resource = RecAbLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BookmarkAction::make(),
        ];
    }
}
