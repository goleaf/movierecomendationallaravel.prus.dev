<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Policies implements ComponentAttribute
{
    /** @var array<int, string> */
    public readonly array $abilities;

    public function __construct(string ...$abilities)
    {
        $this->abilities = array_values(array_filter($abilities, static fn (string $ability): bool => $ability !== ''));
    }

    public function apply(ComponentMetadataBuilder $builder): void
    {
        if ($this->abilities === []) {
            return;
        }

        $builder->policies = array_values(array_unique(array_merge($builder->policies, $this->abilities)));
    }
}
