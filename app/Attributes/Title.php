<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Title implements ComponentAttribute
{
    public function __construct(public readonly string $title) {}

    public function apply(ComponentMetadataBuilder $builder): void
    {
        $builder->title = $this->title;
    }
}
