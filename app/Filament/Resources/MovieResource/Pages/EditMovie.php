<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovieResource\Pages;

use App\Filament\Resources\MovieResource;
use App\Support\TranslationPayload;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Kirschbaum\Commentions\Filament\Actions\CommentsAction;
use Kirschbaum\Commentions\Filament\Actions\SubscriptionAction;

class EditMovie extends EditRecord
{
    protected static string $resource = MovieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommentsAction::make()
                ->mentionables(fn () => MovieResource::getCommentMentionables()),
            SubscriptionAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['translations'] = TranslationPayload::normalize($data['translations'] ?? null);

        return parent::mutateFormDataBeforeFill($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['translations'] = TranslationPayload::prepare($data['translations'] ?? null);

        return parent::mutateFormDataBeforeSave($data);
    }
}
