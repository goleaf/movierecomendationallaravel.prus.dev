<?php

declare(strict_types=1);

namespace App\Attributes;

interface ComponentAttribute
{
    public function apply(ComponentMetadataBuilder $builder): void;
}
