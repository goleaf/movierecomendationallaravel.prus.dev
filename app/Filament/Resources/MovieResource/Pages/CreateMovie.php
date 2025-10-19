<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovieResource\Pages;

use App\Filament\Resources\MovieResource;
use App\Support\TranslationPayload;
use Filament\Resources\Pages\CreateRecord;

class CreateMovie extends CreateRecord
{
    protected static string $resource = MovieResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['translations'] = TranslationPayload::prepare($data['translations'] ?? null);

        return parent::mutateFormDataBeforeCreate($data);
    }
}
