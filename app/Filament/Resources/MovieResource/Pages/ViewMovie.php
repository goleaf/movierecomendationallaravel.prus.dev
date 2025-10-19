<?php

namespace App\Filament\Resources\MovieResource\Pages;

use App\Filament\Resources\MovieResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use TomatoPHP\FilamentBookmarksMenu\Filament\Actions\BookmarkAction;

class ViewMovie extends ViewRecord
{
    protected static string $resource = MovieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BookmarkAction::make(),
            Actions\EditAction::make(),
        ];
    }
}
