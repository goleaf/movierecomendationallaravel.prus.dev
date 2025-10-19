<?php

namespace App\Filament\Resources\RecClickResource\Pages;

use App\Filament\Resources\RecClickResource;
use Filament\Resources\Pages\ListRecords;
use TomatoPHP\FilamentBookmarksMenu\Filament\Actions\BookmarkAction;

class ListRecClicks extends ListRecords
{
    protected static string $resource = RecClickResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BookmarkAction::make(),
        ];
    }
}
