<?php

declare(strict_types=1);

namespace App\Attributes;

final class ComponentMetadataBuilder
{
    public ?string $title = null;

    public ?CacheMetadata $cache = null;

    /** @var array<int, string> */
    public array $policies = [];

    public function build(): ComponentMetadata
    {
        return new ComponentMetadata(
            $this->title,
            $this->cache,
            $this->policies,
        );
    }
}
