<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecClickResource\Pages;

use App\Filament\Resources\RecClickResource;
use Filament\Resources\Pages\ListRecords;

class ListRecClicks extends ListRecords
{
    protected static string $resource = RecClickResource::class;
}
