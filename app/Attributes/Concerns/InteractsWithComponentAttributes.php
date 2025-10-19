<?php

declare(strict_types=1);

namespace App\Attributes\Concerns;

use App\Attributes\ComponentMetadata;

trait InteractsWithComponentAttributes
{
    private ?ComponentMetadata $resolvedComponentMetadata = null;

    protected function componentMetadata(): ComponentMetadata
    {
        return $this->resolvedComponentMetadata ??= ComponentMetadata::for($this);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function layoutData(array $overrides = []): array
    {
        $metadata = $this->componentMetadata();

        if ($metadata->title !== null && ! array_key_exists('title', $overrides)) {
            $overrides = ['title' => $metadata->title] + $overrides;
        }

        return $overrides;
    }
}
