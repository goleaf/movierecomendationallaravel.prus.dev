<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\IngestionRun;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class IdempotencyService
{
    public function findOrStart(string $source, string $externalId, CarbonInterface $dateKey): IngestionRun
    {
        $existing = IngestionRun::query()
            ->forSource($source)
            ->forExternalId($externalId)
            ->forDateKey($dateKey)
            ->first();

        if ($existing instanceof IngestionRun) {
            return $existing;
        }

        return IngestionRun::create([
            'source' => $source,
            'external_id' => $externalId,
            'date_key' => $dateKey->toDateString(),
            'started_at' => now(),
        ]);
    }

    public function recordResult(IngestionRun $run, array $headers, array $payload): IngestionRun
    {
        $etag = $this->extractHeader($headers, 'ETag');
        $lastModified = $this->extractLastModified($headers);

        $run->forceFill([
            'response_headers' => $headers,
            'response_payload' => $payload,
            'last_etag' => $etag,
            'last_modified_at' => $lastModified,
            'finished_at' => now(),
        ])->save();

        return $run->refresh();
    }

    public function lastEtag(IngestionRun $run): ?string
    {
        $value = $run->getAttribute('last_etag');

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    public function lastModifiedAt(IngestionRun $run): ?CarbonInterface
    {
        $value = $run->getAttribute('last_modified_at');

        return $value instanceof CarbonInterface ? $value : null;
    }

    private function extractHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (strcasecmp($key, $name) !== 0) {
                continue;
            }

            if (is_array($value)) {
                $candidate = Arr::first($value);

                return $candidate === null ? null : (string) $candidate;
            }

            return (string) $value;
        }

        return null;
    }

    private function extractLastModified(array $headers): ?CarbonInterface
    {
        $value = $this->extractHeader($headers, 'Last-Modified');

        if ($value === null || Str::of($value)->trim()->isEmpty()) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
