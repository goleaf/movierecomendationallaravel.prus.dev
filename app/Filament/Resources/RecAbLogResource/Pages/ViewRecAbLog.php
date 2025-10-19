<?php

namespace App\Filament\Resources\RecAbLogResource\Pages;

use App\Filament\Resources\RecAbLogResource;
use Filament\Resources\Pages\ViewRecord;
use TomatoPHP\FilamentBookmarksMenu\Filament\Actions\BookmarkAction;

class ViewRecAbLog extends ViewRecord
{
    protected static string $resource = RecAbLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BookmarkAction::make(),
        ];
    }
}
