<?php

namespace App\Filament\Resources\RecClickResource\Pages;

use App\Filament\Resources\RecClickResource;
use Filament\Resources\Pages\ViewRecord;
use TomatoPHP\FilamentBookmarksMenu\Filament\Actions\BookmarkAction;

class ViewRecClick extends ViewRecord
{
    protected static string $resource = RecClickResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BookmarkAction::make(),
        ];
    }
}
