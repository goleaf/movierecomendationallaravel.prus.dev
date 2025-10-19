<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Cache implements ComponentAttribute
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly string $key,
        public readonly int $ttl = 300,
        public readonly array $tags = [],
    ) {}

    public function apply(ComponentMetadataBuilder $builder): void
    {
        $builder->cache = new CacheMetadata($this->key, $this->ttl, $this->tags);
    }
}
