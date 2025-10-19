<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeviceHistoryResource\Pages;

use App\Filament\Resources\DeviceHistoryResource;
use Filament\Resources\Pages\ViewRecord;
use TomatoPHP\FilamentBookmarksMenu\Filament\Actions\BookmarkAction;

class ViewDeviceHistory extends ViewRecord
{
    protected static string $resource = DeviceHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BookmarkAction::make(),
        ];
    }
}
