<?php

declare(strict_types=1);

namespace App\Support;

final class SsrMetricSample
{
    /**
     * @param  array<int, string>  $insights
     */
    public function __construct(
        public readonly string $path,
        public readonly int $score,
        public readonly int $size,
        public readonly int $metaCount,
        public readonly int $openGraphCount,
        public readonly int $ldJsonCount,
        public readonly int $imageCount,
        public readonly int $blockingScripts,
        public readonly array $insights = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDatabasePayload(): array
    {
        return [
            'path' => $this->path,
            'score' => $this->score,
            'size' => $this->size,
            'meta_count' => $this->metaCount,
            'og_count' => $this->openGraphCount,
            'ldjson_count' => $this->ldJsonCount,
            'img_count' => $this->imageCount,
            'blocking_scripts' => $this->blockingScripts,
            'insights' => $this->insights,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshotPayload(string $timestamp): array
    {
        return $this->toDatabasePayload() + [
            'captured_at' => $timestamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toJsonLine(string $timestamp): array
    {
        return $this->toDatabasePayload() + [
            'ts' => $timestamp,
        ];
    }
}
