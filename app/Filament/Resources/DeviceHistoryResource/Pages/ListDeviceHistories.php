<?php

namespace App\Filament\Resources\DeviceHistoryResource\Pages;

use App\Filament\Resources\DeviceHistoryResource;
use Filament\Resources\Pages\ListRecords;
use TomatoPHP\FilamentBookmarksMenu\Filament\Actions\BookmarkAction;

class ListDeviceHistories extends ListRecords
{
    protected static string $resource = DeviceHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BookmarkAction::make(),
        ];
    }
}
