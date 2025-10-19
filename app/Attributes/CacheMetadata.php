<?php

declare(strict_types=1);

namespace App\Attributes;

final class CacheMetadata
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly string $key,
        public readonly int $ttl,
        public readonly array $tags = [],
    ) {}
}
