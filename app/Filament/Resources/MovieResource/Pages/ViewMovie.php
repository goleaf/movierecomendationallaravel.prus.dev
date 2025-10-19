<?php

declare(strict_types=1);

namespace App\Filament\Resources\MovieResource\Pages;

use App\Filament\Resources\MovieResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Kirschbaum\Commentions\Filament\Actions\CommentsAction;
use Kirschbaum\Commentions\Filament\Actions\SubscriptionAction;

class ViewMovie extends ViewRecord
{
    protected static string $resource = MovieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommentsAction::make()
                ->mentionables(fn () => MovieResource::getCommentMentionables())
                ->perPage(10)
                ->loadMoreIncrementsBy(10)
                ->loadMoreLabel('Show older comments'),
            SubscriptionAction::make(),
            Actions\EditAction::make(),
        ];
    }
}
