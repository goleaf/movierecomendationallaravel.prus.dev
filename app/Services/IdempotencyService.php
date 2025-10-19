<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\IdempotencyRecord;
use App\Support\TranslationPayload;
use Carbon\CarbonInterface;

class IdempotencyService
{
    public function find(string $source, string $externalId, string $dateKey): ?IdempotencyRecord
    {
        return IdempotencyRecord::query()
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->whereDate('date_key', $dateKey)
            ->first();
    }

    /**
     * @param  array<int, string>  $locales
     */
    public function shouldShortCircuit(?IdempotencyRecord $record, array $locales): bool
    {
        if ($record === null) {
            return false;
        }

        if ($record->last_etag === null && $record->last_modified_at === null) {
            return false;
        }

        $payload = $record->payload;

        if (! is_array($payload)) {
            return false;
        }

        $translations = TranslationPayload::normalize($payload['translations'] ?? null);

        if ($translations['title'] === [] && $translations['plot'] === []) {
            return false;
        }

        foreach ($locales as $locale) {
            $locale = strtolower($locale);
            $title = $translations['title'][$locale] ?? null;
            $plot = $translations['plot'][$locale] ?? null;

            if ($title === null && $plot === null) {
                return false;
            }
        }

        return true;
    }

    public function persist(
        string $source,
        string $externalId,
        string $dateKey,
        ?string $lastEtag,
        ?CarbonInterface $lastModifiedAt,
        array $payload
    ): IdempotencyRecord {
        return IdempotencyRecord::query()->updateOrCreate(
            [
                'source' => $source,
                'external_id' => $externalId,
                'date_key' => $dateKey,
            ],
            [
                'last_etag' => $lastEtag,
                'last_modified_at' => $lastModifiedAt,
                'payload' => $payload,
            ],
        );
    }
}
