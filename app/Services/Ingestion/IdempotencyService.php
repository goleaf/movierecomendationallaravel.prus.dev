<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\IngestionRun;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Support\Collection;

class IdempotencyService
{
    /**
     * Determine whether the ingestion has already been processed for the provided key on a given day.
     *
     * @param  array<int, string>  $sources
     */
    public function shouldSkip(array $sources, string $externalId, DateTimeInterface|string $date): bool
    {
        $normalizedSources = $this->normalizeSources($sources);
        $day = $this->resolveDate($date);

        return IngestionRun::query()
            ->forKey($normalizedSources, $externalId)
            ->whereDate('ingested_on', $day->toDateString())
            ->exists();
    }

    /**
     * Retrieve the most recent ingestion run for the provided key.
     *
     * @param  array<int, string>  $sources
     */
    public function latest(array $sources, string $externalId): ?IngestionRun
    {
        $normalizedSources = $this->normalizeSources($sources);

        return IngestionRun::query()
            ->forKey($normalizedSources, $externalId)
            ->orderByDesc('ingested_on')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Persist the ingestion metadata to ensure future runs can short-circuit duplicates.
     *
     * @param  array<int, string>  $sources
     */
    public function record(
        array $sources,
        string $externalId,
        DateTimeInterface|string $date,
        ?string $lastEtag,
        ?DateTimeInterface $lastModified
    ): IngestionRun {
        $normalizedSources = $this->normalizeSources($sources);
        $day = $this->resolveDate($date);

        return IngestionRun::query()->updateOrCreate(
            [
                'sources' => $normalizedSources,
                'external_id' => $externalId,
                'ingested_on' => $day->toDateString(),
            ],
            [
                'last_etag' => $lastEtag,
                'last_modified' => $lastModified,
            ]
        );
    }

    private function normalizeSources(array $sources): string
    {
        $normalized = Collection::make($sources)
            ->filter(static fn ($value): bool => is_string($value) && $value !== '')
            ->map(static fn (string $value): string => mb_strtolower($value))
            ->sort()
            ->values()
            ->implode('|');

        return $normalized !== '' ? $normalized : 'default';
    }

    private function resolveDate(DateTimeInterface|string $date): CarbonImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return CarbonImmutable::instance($date)->startOfDay();
        }

        try {
            return CarbonImmutable::parse($date)->startOfDay();
        } catch (InvalidFormatException) {
            return CarbonImmutable::today();
        }
    }
}
