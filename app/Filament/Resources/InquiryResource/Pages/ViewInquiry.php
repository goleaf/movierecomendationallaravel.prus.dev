<?php

namespace App\Filament\Resources\InquiryResource\Pages;

use App\Filament\Resources\InquiryResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewInquiry extends ViewRecord
{
    protected static string $resource = InquiryResource::class;

    protected function afterMount(): void
    {
        if (! $this->record->is_read) {
            $this->record->update(['is_read' => true]);
        }
    }
}
